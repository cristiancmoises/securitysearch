#!/usr/bin/env python3
"""Publish the verified SecuritySearch release to four existing repositories.

Python standard library + Git. Tokens are entered locally, never saved. Run from
the clean source checkout: python3 scripts/publish-release.py --assets dist
"""
import argparse
import getpass
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import secrets
import subprocess
import sys
import tarfile
import tempfile
from urllib.error import HTTPError, URLError
from urllib.parse import quote, urlencode, urljoin, urlsplit
from urllib.request import Request, HTTPRedirectHandler, build_opener

VERSION = "0.9.19"
TAG = "v" + VERSION
PREFIX = "securitysearch-" + TAG
HOSTS = (
    ("github.com", "cristiancmoises", "github"),
    ("git.securityops.co", "cristiancmoises", "forgejo"),
    ("git.securityops.com.br", "cristiancmoises", "forgejo"),
    ("codeberg.org", "berkeley", "forgejo"),
)
ASSET_NAMES = (PREFIX + ".tar.gz", PREFIX + ".tar.gz.sha256")
MAX_ASSET = 256 * 1024 * 1024


class Failure(Exception):
    """A deliberately sanitized user-facing failure."""


def require(condition, message):
    if not condition:
        raise Failure(message)


def sha256(data):
    return hashlib.sha256(data).hexdigest()


def safe_environment():
    env = {k: v for k, v in os.environ.items()
           if not k.startswith(("GIT_", "SECURITYSEARCH_PUBLISH_"))}
    env.update(GIT_CONFIG_NOSYSTEM="1", GIT_CONFIG_GLOBAL=os.devnull,
               GIT_TERMINAL_PROMPT="0", GIT_TRACE="0", GIT_TRACE_CURL="0")
    env.pop("SSH_ASKPASS", None)
    return env


def git(repo, *args, env=None, check=True):
    result = subprocess.run(
        ["git", "-C", str(repo), "-c", "core.hooksPath=" + os.devnull,
         "-c", "credential.helper=", "-c", "http.extraHeader=",
         "-c", "http.followRedirects=false", *args],
        env=env or safe_environment(), stdout=subprocess.PIPE,
        stderr=subprocess.PIPE, timeout=300)
    if check and result.returncode:
        raise Failure("Git operation failed (authentication, permissions, network, or history); no force push was attempted.")
    return result


def text_git(repo, *args):
    return git(repo, *args).stdout.decode("utf-8").strip()


def archive_inventory(data):
    """Compare payloads without extracting anything to disk."""
    result = {}
    with tarfile.open(fileobj=io.BytesIO(data), mode="r:*") as archive:
        require(archive.pax_headers.get("comment"), "Archive has no Git commit provenance.")
        total_size = 0
        for member in archive:
            name = member.name.rstrip("/")
            parts = PurePosixPath(name).parts
            require(parts and parts[0] == PREFIX and ".." not in parts
                    and not name.startswith("/") and name not in result,
                    "Archive contains an unexpected, unsafe, or duplicate path.")
            require(member.isdir() or member.isfile() or member.issym(),
                    "Archive contains an unsupported entry type.")
            require(member.size <= MAX_ASSET, "Archive entry exceeds its size limit.")
            total_size += member.size
            require(len(result) < 50000 and total_size <= 2 * MAX_ASSET,
                    "Archive exceeds its expanded size or entry limit.")
            if member.isfile():
                result[name] = ("file", member.mode & 0o777, sha256(archive.extractfile(member).read()))
            elif member.issym():
                result[name] = ("link", member.mode & 0o777, member.linkname)
            else:
                result[name] = ("dir",)
        return archive.pax_headers["comment"], result


def prepare(repo, assets_dir, notes_path):
    repo = Path(text_git(repo, "rev-parse", "--show-toplevel"))
    require(not text_git(repo, "status", "--porcelain", "--untracked-files=normal"),
            "Source checkout must be clean; commit source changes first.")
    require(text_git(repo, "rev-parse", "--is-shallow-repository") == "false",
            "Use a complete Git checkout; shallow history cannot verify publication ancestry.")
    commit = text_git(repo, "rev-parse", "HEAD")
    require(text_git(repo, "cat-file", "-t", "refs/tags/" + TAG) == "tag",
            "Create the annotated local tag " + TAG + " first.")
    require(text_git(repo, "rev-parse", TAG + "^{commit}") == commit,
            "The annotated release tag must point to HEAD.")
    assets = {}
    for name in ASSET_NAMES:
        path = assets_dir / name
        require(path.is_file() and not path.is_symlink() and 0 < path.stat().st_size <= MAX_ASSET,
                "Missing, empty, unsafe, or oversized release asset: " + name)
        assets[name] = path.read_bytes()
    checksum = assets[ASSET_NAMES[1]].decode("ascii", errors="strict")
    require(checksum in (sha256(assets[ASSET_NAMES[0]]) + "  " + ASSET_NAMES[0] + "\n",
                         sha256(assets[ASSET_NAMES[0]]) + " *" + ASSET_NAMES[0] + "\n"),
            "The SHA256 file does not match the source archive.")
    actual_commit, actual = archive_inventory(assets[ASSET_NAMES[0]])
    expected_bytes = git(repo, "archive", "--format=tar", "--prefix=" + PREFIX + "/", commit).stdout
    expected_commit, expected = archive_inventory(expected_bytes)
    # release.sh adds this required empty cache directory, which Git cannot track.
    expected.setdefault(PREFIX + "/icons", ("dir",))
    require(actual_commit == expected_commit == commit and actual == expected,
            "Archive contents or commit provenance differ from the tagged source.")
    notes_path = notes_path.resolve()
    require(notes_path.is_relative_to(repo.resolve()), "Release notes must be inside the tagged source checkout.")
    relative_notes = notes_path.relative_to(repo.resolve()).as_posix()
    notes = git(repo, "show", commit + ":" + relative_notes).stdout.decode("utf-8").strip()
    require(notes and len(notes) <= 100000, "Release notes are empty or too large.")
    notes += "\n\nCommit: `" + commit + "`\n\nSHA256: `" + sha256(assets[ASSET_NAMES[0]]) + "`\n"
    return repo, commit, assets, notes


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class API:
    def __init__(self, host, owner, kind, token):
        self.host, self.owner, self.kind, self.token = host, owner, kind, token
        self.api_host = "api.github.com" if kind == "github" else host
        self.origin = "https://" + self.api_host
        self.base = self.origin + ("" if kind == "github" else "/api/v1") + "/repos/" + owner + "/securitysearch"
        self.opener = build_opener(NoRedirect())

    def validate_url(self, url, hosts):
        p = urlsplit(url)
        require(p.scheme == "https" and p.hostname in hosts and p.port in (None, 443)
                and not p.username and not p.password and not p.fragment,
                "Refused an unexpected URL or redirect destination.")

    def open(self, method, url, data=None, content_type=None, accept=None, authorized=True):
        self.validate_url(url, {self.api_host, self.host, "uploads.github.com"}
                          if self.kind == "github" else {self.host})
        headers = {"User-Agent": "SecuritySearch-release/" + VERSION,
                   "Accept": accept or "application/json"}
        if authorized:
            headers["Authorization"] = ("Bearer " if self.kind == "github" else "token ") + self.token
        if self.kind == "github":
            headers["X-GitHub-Api-Version"] = "2026-03-10"
        if data is not None:
            headers.update({"Content-Type": content_type or "application/json", "Content-Length": str(len(data))})
        try:
            return self.opener.open(Request(url, data=data, headers=headers, method=method), timeout=120)
        except HTTPError as error:
            return error
        except (URLError, OSError, TimeoutError):
            raise Failure("Network request failed; rerun to reconcile remote state.") from None

    def request(self, method, path="", payload=None, missing=False, url=None, raw=None, mime=None):
        data = raw if raw is not None else (json.dumps(payload).encode() if payload is not None else None)
        with self.open(method, url or self.base + path, data, mime) as response:
            status = response.code
            if missing and status == 404:
                return None
            require(status in (200, 201), "API HTTP " + str(status) + "; check repository access, service availability, or quotas.")
            body = response.read(2 * 1024 * 1024 + 1)
            require(len(body) <= 2 * 1024 * 1024, "API response exceeds the size limit.")
            try:
                return json.loads(body)
            except (ValueError, UnicodeError):
                raise Failure("API returned an invalid JSON response.") from None

    def verify_repository(self):
        repository = self.request("GET")
        require(isinstance(repository, dict), "API returned an invalid repository response.")
        full_name, permissions = repository.get("full_name"), repository.get("permissions")
        require(isinstance(full_name, str) and full_name.casefold() == (self.owner + "/securitysearch").casefold(),
                "API returned the wrong repository owner or name.")
        require(isinstance(permissions, dict) and permissions.get("push") is True,
                "Token has no verified repository write access; use Contents write or write:repository.")

    def find_release(self):
        release = self.request("GET", "/releases/tags/" + quote(TAG, safe=""), missing=True)
        if release is not None:
            return release
        # GitHub's tag lookup can omit drafts. Find a previous partial upload.
        page_key = "per_page" if self.kind == "github" else "limit"
        for page in range(1, 101):
            releases = self.request("GET", "/releases?" + urlencode({page_key: 100, "page": page}))
            require(isinstance(releases, list), "API returned an invalid release list.")
            require(all(isinstance(item, dict) for item in releases), "API returned an invalid release list entry.")
            matches = [item for item in releases if item.get("tag_name") == TAG]
            require(len(matches) < 2, "Multiple releases use the release tag.")
            if matches:
                return matches[0]
            if not releases:
                return None
        raise Failure("Release listing exceeded the pagination limit.")

    def asset_list(self, release_id):
        path = "/releases/" + str(release_id) + "/assets"
        if self.kind != "github":
            assets = self.request("GET", path)
            require(isinstance(assets, list), "API returned an invalid asset list.")
            return assets
        assets = []
        for page in range(1, 101):
            batch = self.request("GET", path + "?per_page=100&page=" + str(page))
            require(isinstance(batch, list), "API returned an invalid asset list.")
            assets.extend(batch)
            if not batch:
                return assets
        raise Failure("Asset listing exceeded the pagination limit.")

    def download_hash(self, url, size):
        self.validate_url(url, {self.api_host, self.host})
        for _ in range(5):
            p = urlsplit(url)
            if p.hostname in {self.api_host, self.host}:
                response = self.open("GET", url, accept="application/octet-stream")
            else:
                # GitHub's signed object URLs receive no Authorization header.
                self.validate_url(url, {"release-assets.githubusercontent.com", "objects.githubusercontent.com"})
                try:
                    response = self.opener.open(Request(url, headers={"User-Agent": "SecuritySearch-release/" + VERSION}), timeout=120)
                except HTTPError as error:
                    response = error
                except (URLError, OSError, TimeoutError):
                    raise Failure("Asset download failed; verification remains pending.") from None
            with response:
                if response.code in (301, 302, 303, 307, 308):
                    location = response.headers.get("Location")
                    require(location, "Asset redirect has no destination.")
                    url = urljoin(url, location)
                    allowed = {self.api_host, self.host}
                    if self.kind == "github":
                        allowed |= {"release-assets.githubusercontent.com", "objects.githubusercontent.com"}
                    self.validate_url(url, allowed)
                    continue
                require(response.code == 200, "Asset download HTTP " + str(response.code) + "; verification remains pending.")
                digest, received = hashlib.sha256(), 0
                while True:
                    chunk = response.read(min(65536, size + 1 - received))
                    if not chunk:
                        break
                    received += len(chunk)
                    require(received <= size, "Remote asset is larger than the local release asset.")
                    digest.update(chunk)
                require(received == size, "Remote asset is truncated.")
                return digest.hexdigest()
        raise Failure("Asset download exceeded the redirect limit.")

    def verify_asset(self, asset, name, data):
        require(isinstance(asset, dict), "API returned an invalid asset response.")
        require(asset.get("name") == name and asset.get("size") == len(data), "Existing release asset name or size differs; no replacement was made.")
        require(asset.get("type") != "external", "Release contains an external link instead of an uploaded asset.")
        if self.kind == "github":
            require(asset.get("state") == "uploaded", "GitHub asset is incomplete; inspect it before retrying.")
        digest = asset.get("digest")
        if digest:
            require(digest == "sha256:" + sha256(data), "Existing asset SHA256 differs; no replacement was made.")
        else:
            asset_id = asset.get("id")
            require(isinstance(asset_id, int) and asset_id > 0, "API returned an invalid asset ID.")
            url = self.base + "/releases/assets/" + str(asset_id) if self.kind == "github" else asset.get("browser_download_url", "")
            require(self.download_hash(url, len(data)) == sha256(data), "Existing asset SHA256 differs; no replacement was made.")

    def upload(self, release, name, data):
        rid = release["id"]
        if self.kind == "github":
            url = release.get("upload_url")
            require(isinstance(url, str), "GitHub returned an invalid upload URL.")
            url = url.split("{", 1)[0]
            expected = "https://uploads.github.com/repos/" + self.owner + "/securitysearch/releases/" + str(rid) + "/assets"
            require(url == expected, "GitHub returned an unexpected upload URL.")
            return self.request("POST", url=url + "?" + urlencode({"name": name}), raw=data, mime="application/octet-stream")
        boundary = "securitysearch-" + secrets.token_hex(24)
        prefix = ('--' + boundary + '\r\nContent-Disposition: form-data; name="attachment"; filename="' + name + '"\r\nContent-Type: application/octet-stream\r\n\r\n').encode()
        body = prefix + data + ("\r\n--" + boundary + "--\r\n").encode()
        return self.request("POST", "/releases/" + str(rid) + "/assets?" + urlencode({"name": name}), raw=body, mime="multipart/form-data; boundary=" + boundary)

    def publish(self, commit, assets, notes):
        release = self.find_release()
        if release is None:
            release = self.request("POST", "/releases", {"tag_name": TAG, "target_commitish": commit,
                                   "name": "SecuritySearch " + TAG, "body": notes, "draft": True, "prerelease": False})
        require(isinstance(release, dict), "API returned an invalid release response.")
        require(release.get("tag_name") == TAG and isinstance(release.get("body"), str)
                and release["body"].replace("\r\n", "\n") == notes,
                "Existing release tag or notes differ; no release was overwritten.")
        require(release.get("name") == "SecuritySearch " + TAG and not release.get("prerelease"),
                "Existing release title or prerelease state differs.")
        rid = release.get("id")
        require(isinstance(rid, int) and rid > 0, "API returned an invalid release ID.")
        existing = self.asset_list(rid)
        require(all(isinstance(item, dict) for item in existing), "API returned an invalid asset list entry.")
        for name, data in assets.items():
            matches = [asset for asset in existing if asset.get("name") == name]
            require(len(matches) <= 1, "Duplicate release asset names require manual review.")
            if not matches:
                require(release.get("draft") is True, "Published release is missing an asset; it was left unchanged.")
                asset = self.upload(release, name, data)
            else:
                asset = matches[0]
            self.verify_asset(asset, name, data)
        # Re-read to confirm both attachments exist before publishing.
        current = self.asset_list(rid)
        require(all(isinstance(item, dict) for item in current), "API returned an invalid asset list entry.")
        for name, data in assets.items():
            matches = [asset for asset in current if asset.get("name") == name]
            require(len(matches) == 1, "Asset verification remains incomplete.")
            self.verify_asset(matches[0], name, data)
        if release.get("draft"):
            release = self.request("PATCH", "/releases/" + str(rid), {"draft": False})
        require(isinstance(release, dict) and release.get("draft") is False, "Release publication remains pending.")


def push_refs(repo, commit, api):
    """An isolated bare repository prevents local remote rewrites or hooks."""
    with tempfile.TemporaryDirectory(prefix="securitysearch-publish-") as directory:
        bare = Path(directory) / "git"
        git(directory, "init", "--bare", str(bare))
        git(bare, "fetch", "--no-tags", str(repo), commit + ":refs/heads/main", "refs/tags/" + TAG + ":refs/tags/" + TAG)
        require(text_git(bare, "rev-parse", TAG + "^{commit}") == commit, "Local release tag changed during publication.")
        askpass = Path(directory) / "askpass"
        askpass.write_text("#!" + sys.executable + "\nimport os,sys\np=sys.argv[1].lower() if len(sys.argv)>1 else ''\nif os.environ['SECURITYSEARCH_PUBLISH_HOST'].lower() not in p: sys.exit(1)\nif 'username' in p: print(os.environ['SECURITYSEARCH_PUBLISH_USER'])\nelif 'password' in p: print(os.environ['SECURITYSEARCH_PUBLISH_TOKEN'])\nelse: sys.exit(1)\n")
        askpass.chmod(0o700)
        env = safe_environment()
        env.update(GIT_ASKPASS=str(askpass), LC_ALL="C", SECURITYSEARCH_PUBLISH_HOST=api.host,
                   SECURITYSEARCH_PUBLISH_USER=api.owner, SECURITYSEARCH_PUBLISH_TOKEN=api.token)
        url = "https://" + api.host + "/" + api.owner + "/securitysearch.git"
        output = git(bare, "ls-remote", url, "refs/heads/main", "refs/tags/" + TAG, env=env).stdout.decode()
        refs = dict(line.split()[::-1] for line in output.splitlines() if line.strip())
        remote_tag = refs.get("refs/tags/" + TAG)
        local_tag = text_git(bare, "rev-parse", "refs/tags/" + TAG)
        require(not remote_tag or remote_tag == local_tag, "Remote tag differs; it will not be moved or replaced.")
        if "refs/heads/main" in refs:
            git(bare, "fetch", "--no-tags", url, "refs/heads/main:refs/remotes/publish/main", env=env)
            require(git(bare, "merge-base", "--is-ancestor", "refs/remotes/publish/main", commit, check=False).returncode == 0,
                    "Remote main contains commits absent from this release; merge them before publishing.")
        git(bare, "push", "--atomic", url, commit + ":refs/heads/main", "refs/tags/" + TAG + ":refs/tags/" + TAG, env=env)
        output = git(bare, "ls-remote", url, "refs/heads/main", "refs/tags/" + TAG, env=env).stdout.decode()
        refs = dict(line.split()[::-1] for line in output.splitlines() if line.strip())
        require(refs.get("refs/heads/main") == commit and refs.get("refs/tags/" + TAG) == local_tag,
                "Remote refs changed during verification; release publication was stopped.")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--assets", type=Path, default=Path("dist"))
    parser.add_argument("--notes", type=Path, default=Path("docs/RELEASE-" + VERSION + ".md"))
    args = parser.parse_args()
    if not sys.stdin.isatty():
        print("STOP: run from an interactive terminal so token entry stays hidden.", file=sys.stderr)
        return 1
    try:
        repo, commit, assets, notes = prepare(Path.cwd(), args.assets.resolve(), args.notes)
    except (Failure, OSError, ValueError, tarfile.TarError, subprocess.SubprocessError) as error:
        print("STOP: " + (str(error) if isinstance(error, Failure) else "Local release validation failed."), file=sys.stderr)
        return 1
    print("Verified " + TAG + " at " + commit + ". Publishing main, tag, source archive, and checksum.")
    failed = []
    for host, owner, kind in HOSTS:
        print("\n" + host + " / " + owner + "/securitysearch", flush=True)
        token = getpass.getpass("Token (hidden; blank skips this host): ").strip()
        if not token:
            failed.append(host)
            print("PENDING: no token provided.")
            continue
        try:
            require(all(32 < ord(character) < 127 for character in token),
                    "Token contains whitespace, control characters, or non-ASCII characters.")
            api = API(host, owner, kind, token)
            api.verify_repository()
            push_refs(repo, commit, api)
            api.publish(commit, assets, notes)
            print("OK: https://" + host + "/" + owner + "/securitysearch/releases/tag/" + TAG)
        except (Failure, OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError):
            # Keep server bodies, subprocess diagnostics, URLs with signatures,
            # tokens, and arbitrary exception representations out of the log.
            error = sys.exc_info()[1]
            print("PENDING: " + (str(error) if isinstance(error, Failure) else "Publication failed; rerun to reconcile remote state."))
            failed.append(host)
        finally:
            token = None
            api = None
    print("\n" + ("All four releases verified." if not failed else "Pending hosts: " + ", ".join(failed)))
    return 1 if failed else 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (KeyboardInterrupt, EOFError):
        print("\nInterrupted; completed hosts are preserved. Rerun to resume.", file=sys.stderr)
        sys.exit(130)
