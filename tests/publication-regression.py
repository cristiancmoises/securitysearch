#!/usr/bin/env python3
"""Network-free release state, credential-boundary, archive and Git tests."""
import copy
from email import policy
from email.parser import BytesParser
import gzip
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest
from unittest.mock import patch
from urllib.parse import parse_qs, urlsplit

spec = importlib.util.spec_from_file_location("publisher", Path(__file__).resolve().parents[1] / "scripts/publish-release.py")
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)


class FakeAPI(p.API):
    def __init__(self, kind="github"):
        super().__init__("github.com" if kind == "github" else "codeberg.org", "cristiancmoises" if kind == "github" else "berkeley", kind, "SECRET-NOT-FOR-LOGS")
        self.release = None
        self.assets = []
        self.payloads = {}
        self.writes = []
        self.fail_upload = None
        self.lose_create_response = False

    def request(self, method, path="", payload=None, **kwargs):
        if method == "GET" and not path:
            return {"full_name": self.owner + "/securitysearch", "permissions": {"push": True}}
        if method == "GET" and path.startswith("/releases/tags/"):
            return copy.deepcopy(self.release) if self.release and not self.release["draft"] else None
        if method == "GET" and path.startswith("/releases?"):
            return [copy.deepcopy(self.release)] if self.release and parse_qs(urlsplit(path).query).get("page") == ["1"] else []
        if method == "GET" and "/assets" in path:
            if self.kind == "github" and parse_qs(urlsplit(path).query).get("page") != ["1"]:
                return []
            return copy.deepcopy(self.assets)
        if method == "POST" and path == "/releases":
            self.writes.append(("create",))
            self.release = dict(payload, id=42, upload_url="https://uploads.github.com/repos/" + self.owner + "/securitysearch/releases/42/assets{?name,label}")
            if self.lose_create_response:
                self.lose_create_response = False
                raise p.Failure("Network request failed; rerun to reconcile remote state.")
            return copy.deepcopy(self.release)
        if method == "PATCH":
            self.writes.append(("publish",))
            self.release.update(payload)
            return copy.deepcopy(self.release)
        raise AssertionError((method, path))

    def upload(self, release, name, data):
        self.writes.append(("upload", name))
        if self.fail_upload == name:
            self.fail_upload = None
            raise p.Failure("API HTTP 502; upstream unavailable.")
        asset = dict(id=len(self.assets) + 1, name=name, size=len(data), state="uploaded", type="attachment",
                     browser_download_url="https://" + self.host + "/attachments/fixture")
        if self.kind == "github":
            asset["digest"] = "sha256:" + p.sha256(data)
        self.assets.append(asset)
        self.payloads[name] = data
        return copy.deepcopy(asset)

    def download_hash(self, url, size):
        matches = [asset for asset in self.assets if asset["size"] == size]
        return p.sha256(self.payloads[matches[0]["name"]])


class Response(io.BytesIO):
    def __init__(self, data=b"", code=200, headers=None):
        super().__init__(data)
        self.code = code
        self.headers = headers or {}


class ReleaseTests(unittest.TestCase):
    def setUp(self):
        self.assets = {p.ASSET_NAMES[0]: b"tar payload", p.ASSET_NAMES[1]: b"checksum payload"}
        self.notes = "Release notes\nNotas da versao\n"

    def test_exact_rerun_has_no_writes(self):
        for kind in ("github", "forgejo"):
            api = FakeAPI(kind)
            api.publish("a" * 40, self.assets, self.notes)
            self.assertFalse(api.release["draft"])
            writes = copy.deepcopy(api.writes)
            api.publish("a" * 40, self.assets, self.notes)
            self.assertEqual(api.writes, writes)

    def test_partial_upload_resume_keeps_first_asset(self):
        for kind in ("github", "forgejo"):
            api = FakeAPI(kind)
            api.fail_upload = p.ASSET_NAMES[1]
            with self.assertRaises(p.Failure):
                api.publish("a" * 40, self.assets, self.notes)
            self.assertTrue(api.release["draft"])
            self.assertEqual(len(api.assets), 1)
            api.publish("a" * 40, self.assets, self.notes)
            self.assertFalse(api.release["draft"])
            self.assertEqual(api.writes.count(("upload", p.ASSET_NAMES[0])), 1)

    def test_lost_create_response_reconciles_draft(self):
        api = FakeAPI()
        api.lose_create_response = True
        with self.assertRaises(p.Failure):
            api.publish("a" * 40, self.assets, self.notes)
        api.publish("a" * 40, self.assets, self.notes)
        self.assertEqual(api.writes.count(("create",)), 1)

    def test_wrong_release_or_digest_never_overwritten(self):
        for field, value in (("tag_name", "v0.0.0"), ("body", "different notes")):
            api = FakeAPI()
            api.publish("a" * 40, self.assets, self.notes)
            api.release[field] = value
            before = copy.deepcopy(api.writes)
            with self.assertRaises(p.Failure):
                api.publish("a" * 40, self.assets, self.notes)
            self.assertEqual(api.writes, before)
        for kind in ("github", "forgejo"):
            api = FakeAPI(kind)
            api.publish("a" * 40, self.assets, self.notes)
            if kind == "github":
                api.assets[0]["digest"] = "sha256:" + "0" * 64
            else:
                api.payloads[p.ASSET_NAMES[0]] = b"TAR PAYLOAD"
            with self.assertRaises(p.Failure):
                api.publish("a" * 40, self.assets, self.notes)

    def test_wrong_host_rejected_before_network(self):
        api = p.API("github.com", "cristiancmoises", "github", "SECRET")
        for url in ("https://evil.example/", "http://api.github.com/", "https://api.github.com@evil.example/", "https://api.github.com:444/", "https://api.github.com/#fragment"):
            with self.assertRaises(p.Failure):
                api.open("GET", url)
        release = {"id": 42, "upload_url": "https://evil.example/upload"}
        with self.assertRaises(p.Failure):
            api.upload(release, p.ASSET_NAMES[0], b"data")

    def test_signed_download_strips_authorization(self):
        api = p.API("github.com", "cristiancmoises", "github", "SECRET")
        seen = []
        def open_request(request, timeout):
            seen.append(request)
            if len(seen) == 1:
                return Response(code=302, headers={"Location": "https://release-assets.githubusercontent.com/asset?signature=opaque"})
            return Response(b"data")
        with patch.object(api.opener, "open", side_effect=open_request):
            self.assertEqual(api.download_hash(api.base + "/releases/assets/1", 4), p.sha256(b"data"))
        self.assertEqual(seen[0].get_header("Authorization"), "Bearer SECRET")
        self.assertIsNone(seen[1].get_header("Authorization"))

    def test_forgejo_upload_is_valid_multipart(self):
        api = p.API("codeberg.org", "berkeley", "forgejo", "SECRET")
        captured = []
        def response(request, timeout):
            captured.append(request)
            return Response(json.dumps({"id": 7, "name": p.ASSET_NAMES[0], "size": 4}).encode(), 201)
        with patch.object(api.opener, "open", side_effect=response):
            api.upload({"id": 42}, p.ASSET_NAMES[0], b"data")
        request = captured[0]
        message = BytesParser(policy=policy.default).parsebytes(
            ("Content-Type: " + request.get_header("Content-type") + "\r\n\r\n").encode() + request.data)
        attachment = list(message.iter_parts())[0]
        self.assertEqual(attachment.get_param("name", header="Content-Disposition"), "attachment")
        self.assertEqual(attachment.get_filename(), p.ASSET_NAMES[0])
        self.assertEqual(attachment.get_payload(decode=True), b"data")
        self.assertEqual(request.get_header("Content-length"), str(len(request.data)))

    def test_malicious_redirect_does_not_receive_token(self):
        api = p.API("codeberg.org", "berkeley", "forgejo", "SECRET")
        with patch.object(api.opener, "open", return_value=Response(code=302, headers={"Location": "https://evil.example/"})) as opener:
            with self.assertRaises(p.Failure):
                api.download_hash("https://codeberg.org/attachments/1", 4)
            self.assertEqual(opener.call_count, 1)

    def test_main_continues_after_one_host_failure_without_secret_log(self):
        class HostAPI(FakeAPI):
            def __init__(self, host, owner, kind, token):
                super().__init__(kind)
                self.host = host
            def verify_repository(self):
                if self.host == "github.com":
                    raise p.Failure("API HTTP 403; repository access denied.")
        output = io.StringIO()
        with patch.object(p, "prepare", return_value=(Path("."), "a" * 40, self.assets, self.notes)), \
             patch.object(p, "push_refs"), patch.object(p, "API", HostAPI), \
             patch.object(p.getpass, "getpass", return_value="SECRET"), \
             patch.object(p.sys.stdin, "isatty", return_value=True), \
             patch.object(p.sys, "argv", ["publish-release.py"]), patch.object(p.sys, "stdout", output):
            self.assertEqual(p.main(), 1)
        self.assertEqual(output.getvalue().count("OK: https://"), 3)
        self.assertNotIn("SECRET", output.getvalue())
        self.assertIn("Pending hosts: github.com", output.getvalue())


class GitAndArchiveTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        self.repo = self.root / "source"
        self.repo.mkdir()
        self.git(self.repo, "init", "-b", "main")
        self.git(self.repo, "config", "user.name", "Fixture")
        self.git(self.repo, "config", "user.email", "fixture@example.invalid")
        (self.repo / "docs").mkdir()
        self.notes = self.repo / "docs" / ("RELEASE-" + p.VERSION + ".md")
        self.notes.write_text("Release notes\nNotas da versao\n")
        (self.repo / "index.php").write_text("<?php echo 'fixture';\n")
        self.git(self.repo, "add", ".")
        self.git(self.repo, "commit", "-m", "Fixture source")
        self.git(self.repo, "tag", "-a", p.TAG, "-m", "Fixture release")
        self.commit = self.git(self.repo, "rev-parse", "HEAD").decode().strip()
        self.assets = self.root / "assets"
        self.assets.mkdir()
        self.build_archive()

    def git(self, repo, *args):
        result = subprocess.run(["git", "-C", str(repo), *args], stdout=subprocess.PIPE, stderr=subprocess.PIPE, env=p.safe_environment(), check=True)
        return result.stdout

    def build_archive(self):
        raw = self.git(self.repo, "archive", "--format=tar", "--prefix=" + p.PREFIX + "/", self.commit)
        data = io.BytesIO(raw)
        with tarfile.open(fileobj=data, mode="a") as archive:
            info = tarfile.TarInfo(p.PREFIX + "/icons")
            info.type = tarfile.DIRTYPE
            archive.addfile(info)
        compressed = gzip.compress(data.getvalue(), mtime=0)
        (self.assets / p.ASSET_NAMES[0]).write_bytes(compressed)
        (self.assets / p.ASSET_NAMES[1]).write_text(p.sha256(compressed) + "  " + p.ASSET_NAMES[0] + "\n")

    def test_matching_archive_and_annotated_tag(self):
        repo, commit, assets, notes = p.prepare(self.repo, self.assets, self.notes)
        self.assertEqual(commit, self.commit)
        self.assertEqual(len(assets), 2)
        self.assertIn(self.commit, notes)

    def test_archive_checksum_and_tag_mismatch_fail(self):
        checksum = self.assets / p.ASSET_NAMES[1]
        checksum.write_text("0" * 64 + "  " + p.ASSET_NAMES[0] + "\n")
        with self.assertRaises(p.Failure):
            p.prepare(self.repo, self.assets, self.notes)
        self.build_archive()
        self.git(self.repo, "commit", "--allow-empty", "-m", "Later source")
        with self.assertRaises(p.Failure):
            p.prepare(self.repo, self.assets, self.notes)

    def test_modified_archive_with_updated_checksum_fails_payload_check(self):
        raw = gzip.decompress((self.assets / p.ASSET_NAMES[0]).read_bytes())
        raw = raw.replace(b"<?php echo 'fixture';", b"<?php echo 'changed';")
        changed = gzip.compress(raw, mtime=0)
        (self.assets / p.ASSET_NAMES[0]).write_bytes(changed)
        (self.assets / p.ASSET_NAMES[1]).write_text(p.sha256(changed) + "  " + p.ASSET_NAMES[0] + "\n")
        with self.assertRaises(p.Failure):
            p.prepare(self.repo, self.assets, self.notes)

    def test_normal_push_rerun_and_divergent_remote(self):
        remote = self.root / "remote.git"
        self.git(self.root, "init", "--bare", str(remote))
        api = p.API("codeberg.org", "berkeley", "forgejo", "SECRET")
        actual_git = p.git
        def local_git(repo, *args, **kwargs):
            # Replace only the expected fixed network URL in this test.
            args = tuple(str(remote) if item == "https://codeberg.org/berkeley/securitysearch.git" else item for item in args)
            if kwargs.get("env", {}).get("SECURITYSEARCH_PUBLISH_TOKEN"):
                script = Path(kwargs["env"]["GIT_ASKPASS"])
                self.assertNotIn("SECRET", script.read_text())
                self.assertNotIn("GIT_CURL_VERBOSE", kwargs["env"])
            self.assertNotIn("--force", args)
            return actual_git(repo, *args, **kwargs)
        with patch.object(p, "git", side_effect=local_git):
            p.push_refs(self.repo, self.commit, api)
            p.push_refs(self.repo, self.commit, api)
            self.assertEqual(self.git(remote, "rev-parse", "refs/heads/main").decode().strip(), self.commit)
            self.git(self.repo, "commit", "--allow-empty", "-m", "Remote-only change")
            self.git(self.repo, "push", str(remote), "HEAD:refs/heads/main")
            with self.assertRaisesRegex(p.Failure, "absent"):
                p.push_refs(self.repo, self.commit, api)

    def test_existing_remote_tag_is_never_replaced(self):
        remote = self.root / "remote-tag.git"
        self.git(self.root, "init", "--bare", str(remote))
        self.git(self.repo, "push", str(remote), "HEAD:refs/heads/main", "HEAD:refs/tags/" + p.TAG)
        before = self.git(remote, "rev-parse", "refs/tags/" + p.TAG)
        actual_git = p.git
        def local_git(repo, *args, **kwargs):
            args = tuple(str(remote) if item == "https://codeberg.org/berkeley/securitysearch.git" else item for item in args)
            return actual_git(repo, *args, **kwargs)
        with patch.object(p, "git", side_effect=local_git), self.assertRaisesRegex(p.Failure, "Remote tag differs"):
            p.push_refs(self.repo, self.commit, p.API("codeberg.org", "berkeley", "forgejo", "SECRET"))
        self.assertEqual(self.git(remote, "rev-parse", "refs/tags/" + p.TAG), before)


if __name__ == "__main__":
    unittest.main(verbosity=2)
