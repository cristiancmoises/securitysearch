#!/usr/bin/env python3
"""Offline integration tests for the pinned fish publication-kit launcher."""
import gzip
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

SOURCE = Path(os.environ.get("SOURCE_REPO", Path(__file__).resolve().parents[1]))
spec = importlib.util.spec_from_file_location("publication_builder", SOURCE / "scripts/build-publication-kit.py")
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


@unittest.skipUnless(shutil.which("fish"), "fish is required for launcher integration tests")
class PublicationKitTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix="publication-kit-test-")
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        # Spaces exercise quoting in the launcher, bundle and assets argument.
        self.source, self.work, self.kit = [self.root / name for name in ("release source", "user checkout", "publication kit")]
        self.env = {key: value for key, value in os.environ.items() if not key.startswith("GIT_")}
        self.env.update(GIT_CONFIG_NOSYSTEM="1", GIT_CONFIG_GLOBAL=os.devnull,
                        GIT_TERMINAL_PROMPT="0", LC_ALL="C")
        for directory in (self.source, self.work):
            directory.mkdir()
            self.git(directory, "init", "-b", "main")
            self.git(directory, "config", "user.name", "Publication Fixture")
            self.git(directory, "config", "user.email", "fixture@example.invalid")
        self.kit.mkdir()
        (self.source / "static/misc").mkdir(parents=True)
        (self.source / "static/misc/lain.gifv").write_text("removed fixture\n")
        (self.source / "README.md").write_text("old source\n")
        self.commit(self.source, "Public base")
        self.base = self.git(self.source, "rev-parse", "HEAD")
        self.git(self.work, "fetch", "--no-tags", str(self.source), self.base)
        self.git(self.work, "checkout", "-B", "main", "FETCH_HEAD")
        (self.source / "static/misc/lain.gifv").unlink()
        (self.source / "README.md").write_text("new release\n")
        (self.source / "scripts").mkdir()
        (self.source / "scripts/publish-release.py").write_text(
            "import json,os,pathlib,subprocess,sys\n"
            "record={'argv':sys.argv[1:],'cwd':os.getcwd(),'head':subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()}\n"
            "with pathlib.Path(os.environ['PUBLISH_TEST_LOG']).open('a') as log: log.write(json.dumps(record)+'\\n')\n")
        self.commit(self.source, "Prepare release and retain asset deletion")
        self.head = self.git(self.source, "rev-parse", "HEAD")
        self.git(self.source, "tag", "-a", builder.TAG, "-m", "Publication fixture")
        self.tag_object = self.git(self.source, "rev-parse", builder.TAG)
        self.archive = self.kit / ("securitysearch-" + builder.TAG + ".tar.gz")
        raw = subprocess.check_output(["git", "-C", str(self.source), "archive", "--format=tar", "--prefix=securitysearch-" + builder.TAG + "/", self.head], env=self.env)
        self.archive.write_bytes(gzip.compress(raw, mtime=0))
        self.bundle = self.kit / ("securitysearch-" + builder.TAG + ".bundle")
        self.git(self.source, "bundle", "create", str(self.bundle), "refs/heads/main", "refs/tags/" + builder.TAG, "^" + self.base)
        self.launcher = self.kit / ("publish-securitysearch-" + builder.TAG + ".fish")
        self.render()
        self.log = self.root / "publisher-invocations.jsonl"
        self.env["PUBLISH_TEST_LOG"] = str(self.log)

    def git(self, directory, *args):
        result = subprocess.run(["git", "-C", str(directory), *args], env=self.env,
                                stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        return result.stdout.strip()

    def commit(self, directory, message):
        self.git(directory, "add", "-A")
        self.git(directory, "commit", "-m", message)

    def render(self, **overrides):
        values = {"VERSION": builder.VERSION, "ARCHIVE_SHA": hashlib.sha256(self.archive.read_bytes()).hexdigest(),
                  "BUNDLE_SHA": hashlib.sha256(self.bundle.read_bytes()).hexdigest(), "COMMIT": self.head,
                  "TAG_OBJECT": self.tag_object}
        values.update(overrides)
        launcher = builder.LAUNCHER
        for key, value in values.items():
            launcher = launcher.replace("@" + key + "@", value)
        self.assertNotIn("@VERSION@", launcher)
        self.launcher.write_text(launcher)

    def run_launcher(self):
        return subprocess.run(["fish", str(self.launcher), str(self.work)], env=self.env,
                              cwd=self.root, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=30)

    def assert_refused(self):
        before = self.git(self.work, "rev-parse", "HEAD")
        result = self.run_launcher()
        self.assertNotEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual(self.git(self.work, "rev-parse", "HEAD"), before)
        self.assertFalse(self.log.exists(), "Publisher must not run after rejected import")
        self.assertFalse(self.git(self.work, "for-each-ref", "--format=%(refname)", "refs/heads/backup/"))
        return result

    def test_import_preserves_deletion_tag_backup_and_rerun(self):
        self.assertTrue((self.work / "static/misc/lain.gifv").exists())
        result = self.run_launcher()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual(self.git(self.work, "rev-parse", "HEAD"), self.head)
        self.assertFalse((self.work / "static/misc/lain.gifv").exists())
        self.assertEqual(self.git(self.work, "cat-file", "-t", builder.TAG), "tag")
        self.assertEqual(self.git(self.work, "rev-parse", builder.TAG), self.tag_object)
        backups = self.git(self.work, "for-each-ref", "--format=%(objectname) %(refname)", "refs/heads/backup/").splitlines()
        self.assertEqual(len(backups), 1)
        self.assertTrue(backups[0].startswith(self.base + " refs/heads/backup/securitysearch-before-" + builder.TAG + "-"))
        result = self.run_launcher()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual(self.git(self.work, "for-each-ref", "--format=%(objectname) %(refname)", "refs/heads/backup/").splitlines(), backups)
        records = [json.loads(line) for line in self.log.read_text().splitlines()]
        self.assertEqual(len(records), 2)
        for record in records:
            self.assertEqual(record, {"argv": ["--assets", str(self.kit)], "cwd": str(self.work), "head": self.head})
        self.assertEqual(self.git(self.work, "status", "--porcelain"), "")

    def test_dirty_tracked_source_is_preserved(self):
        path = self.work / "README.md"
        path.write_text("local unfinished work\n")
        self.assert_refused()
        self.assertEqual(path.read_text(), "local unfinished work\n")

    def test_untracked_source_is_preserved(self):
        path = self.work / "local-notes.txt"
        path.write_text("keep me\n")
        self.assert_refused()
        self.assertEqual(path.read_text(), "keep me\n")

    def test_diverged_history_is_preserved(self):
        (self.work / "local-change.txt").write_text("local change\n")
        self.commit(self.work, "User's additional commit")
        result = self.assert_refused()
        self.assertIn("additional commits", result.stderr)

    def test_wrong_archive_hash_refuses_before_import(self):
        self.archive.write_bytes(self.archive.read_bytes() + b"changed")
        self.assert_refused()

    def test_wrong_bundle_hash_refuses_before_import(self):
        self.bundle.write_bytes(self.bundle.read_bytes() + b"changed")
        self.assert_refused()

    def test_conflicting_tag_is_preserved(self):
        self.git(self.work, "tag", "-a", builder.TAG, "-m", "Existing different tag")
        old = self.git(self.work, "rev-parse", builder.TAG)
        self.assert_refused()
        self.assertEqual(self.git(self.work, "rev-parse", builder.TAG), old)

    def test_wrong_pinned_commit_refuses_after_bundle_verification(self):
        self.render(COMMIT="0" * 40)
        self.assert_refused()

    def test_wrong_pinned_tag_object_refuses_after_bundle_verification(self):
        self.render(TAG_OBJECT="0" * 40)
        self.assert_refused()


if __name__ == "__main__":
    unittest.main(verbosity=2)
