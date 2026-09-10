#!/usr/bin/env python3
"""Run operator-theme tests from both archive and checkout layouts, without network.

The archive fixture contains the unchanged source files needed by the operator
suite. It does not download historical imagery or create Git metadata in ROOT.
"""
from pathlib import Path
import hashlib
import os
import subprocess
import sys
import tarfile
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
FILES = (
    'tests/operator-themes-regression.py',
    'scripts/operator_themes.py',
    'scripts/release_media_policy.py',
    'lib/operator_themes.php',
    'static/misc/tron.gif',
)


class SourceLayouts(unittest.TestCase):
    def check_layout(self, checkout: bool) -> None:
        with tempfile.TemporaryDirectory(prefix='securitysearch-archive-test-') as tmp:
            parent = Path(tmp)
            tree = parent / 'source'
            tree.mkdir()
            archive = parent / 'source.tar'
            with tarfile.open(archive, 'w') as tar:
                for name in FILES:
                    path = ROOT / name
                    self.assertTrue(path.is_file() and not path.is_symlink(), name)
                    tar.add(path, arcname=name, recursive=False)
            with tarfile.open(archive) as tar:
                # Copy only our fixed regular-file allowlist (also works on Python 3.10).
                for name in FILES:
                    item = tar.getmember(name)
                    self.assertTrue(item.isfile(), name)
                    target = tree / name
                    target.parent.mkdir(parents=True, exist_ok=True)
                    with tar.extractfile(item) as content:
                        target.write_bytes(content.read())
            self.assertFalse((tree / '.git').exists())
            before = {name: hashlib.sha256((tree / name).read_bytes()).hexdigest() for name in FILES}
            env = {key: value for key, value in os.environ.items() if not key.startswith('GIT_')}
            env.update(GIT_CONFIG_NOSYSTEM='1', GIT_CONFIG_GLOBAL=os.devnull,
                       GIT_TERMINAL_PROMPT='0', GIT_CEILING_DIRECTORIES=str(parent),
                       PYTHONDONTWRITEBYTECODE='1')
            if checkout:
                subprocess.run(['git', 'init', '--quiet', '--template=', str(tree)],
                               env=env, check=True, capture_output=True, timeout=10)
            probe = subprocess.run(['git', '-C', str(tree), 'rev-parse', '--show-toplevel'],
                                   env=env, text=True, capture_output=True, timeout=10)
            self.assertEqual(probe.returncode == 0, checkout)
            result = subprocess.run([sys.executable, '-B', 'tests/operator-themes-regression.py'],
                                    cwd=tree, env=env, text=True, capture_output=True, timeout=60)
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            self.assertEqual((tree / '.git').exists(), checkout)
            self.assertEqual(before, {name: hashlib.sha256((tree / name).read_bytes()).hexdigest() for name in FILES})
            self.assertFalse((tree / 'static/operator-themes').exists())
            self.assertEqual(sorted(str(p.relative_to(tree)) for p in tree.rglob('*')
                                    if p.is_file() and '.git' not in p.relative_to(tree).parts), sorted(FILES))

    def test_suite_from_source_archive_without_git_metadata(self):
        self.check_layout(False)

    def test_suite_from_checkout_with_git_metadata(self):
        self.check_layout(True)


if __name__ == '__main__':
    unittest.main(verbosity=2)
