#!/usr/bin/env python3
"""Exercise the real optional-pack validator under file imports and archive layouts.

Docker/SSH are never called. Transaction child tests use the existing simulated
engine. Tiny local motion bytes stand in for a private pack; no artwork is fetched.
"""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import types
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]


def load(path):
    spec = importlib.util.spec_from_file_location('fixture_deployer', path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def make_pack(root):
    directory = root / 'static/operator-themes'
    directory.mkdir(parents=True)
    names = [name for theme in ('Lain', 'SecOps') for name in (
        theme.lower() + '.webp', theme.lower() + '-still.webp', theme + '-preview.webp')]
    data = (ROOT / 'tests/fixtures/motion/two.webp').read_bytes()
    for name in names:
        (directory / name).write_bytes(data)
    manifest = {
        'schema': 1, 'deployment_only': True,
        'source_commit': '81979bb217f97df1c6acc724ef7d8c9da2789d7c',
        'source_blobs': {'Lain': 'fcd2163ef4f77991b0f66af12099bd13e7322b3c',
                         'SecOps': 'b5e32642d24b7e31bdba2a4c06aa7aad1d41cb9f'},
        'assets': {name: {'size': len(data), 'sha256': hashlib.sha256(data).hexdigest()}
                   for name in names},
    }
    (directory / 'manifest.json').write_text(json.dumps(manifest))
    return directory, manifest


class ImportAndPack(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='securitysearch-import-')
        self.root = Path(self.temp.name) / 'source'
        (self.root / 'scripts').mkdir(parents=True)
        for name in ('deploy-ionos.py', 'operator_themes.py'):
            shutil.copy2(ROOT / 'scripts' / name, self.root / 'scripts' / name)
        self.path = self.root / 'scripts/deploy-ionos.py'
        self.module = load(self.path)
        self.pack, self.manifest = make_pack(self.root)

    def tearDown(self):
        self.temp.cleanup()

    def test_actual_validator_accepts_pack_without_git(self):
        self.assertFalse((self.root / '.git').exists())
        self.assertEqual(self.module.validate_operator_pack(self.pack), self.manifest)
        self.assertFalse((self.root / '.git').exists())

    def test_unrelated_cwd_and_poisoned_pythonpath(self):
        outside = Path(self.temp.name) / 'outside'
        outside.mkdir()
        (outside / 'operator_themes.py').write_text('raise AssertionError("shadow was loaded")\n')
        code = ('import importlib.util, sys\n'
                's=importlib.util.spec_from_file_location("dep", sys.argv[1])\n'
                'm=importlib.util.module_from_spec(s); s.loader.exec_module(m)\n'
                'assert m.validate_operator_pack(sys.argv[2])["deployment_only"] is True\n')
        env = dict(os.environ, PYTHONPATH=str(outside), PYTHONDONTWRITEBYTECODE='1')
        result = subprocess.run([sys.executable, '-B', '-c', code, str(self.path), str(self.pack)],
                                cwd=outside, env=env, capture_output=True, text=True, timeout=20)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_cached_same_named_modules_are_not_used(self):
        poison = types.ModuleType('operator_themes')
        poison.validate = lambda _: self.fail('ambient module executed')
        original_path = list(sys.path)
        with patch.dict(sys.modules, {'operator_themes': poison,
                        '_securitysearch_deploy_operator_themes': poison}):
            self.assertEqual(self.module.validate_operator_pack(self.pack), self.manifest)
            self.assertIs(sys.modules['operator_themes'], poison)
            self.assertIs(sys.modules['_securitysearch_deploy_operator_themes'], poison)
        self.assertEqual(sys.path, original_path)

    def test_missing_helper_is_not_satisfied_by_ambient_module(self):
        (self.root / 'scripts/operator_themes.py').unlink()
        fake = types.ModuleType('operator_themes')
        fake.validate = lambda _: self.manifest
        with patch.dict(sys.modules, {'operator_themes': fake}):
            with self.assertRaisesRegex(RuntimeError, 'helper is missing or unsafe'):
                self.module.validate_operator_pack(self.pack)

    def test_linked_helper_refused(self):
        helper = self.root / 'scripts/operator_themes.py'
        target = Path(self.temp.name) / 'helper.py'
        helper.rename(target)
        helper.symlink_to(target)
        with self.assertRaisesRegex(RuntimeError, 'helper is missing or unsafe'):
            self.module.validate_operator_pack(self.pack)

    def test_helper_without_validator_refused(self):
        (self.root / 'scripts/operator_themes.py').write_text('UNRELATED = True\n')
        with self.assertRaisesRegex(RuntimeError, 'no validate function'):
            self.module.validate_operator_pack(self.pack)

    def test_modified_pack_refused_before_docker(self):
        path = self.pack / 'lain.webp'
        raw = path.read_bytes()
        path.write_bytes(raw[:-1] + bytes([raw[-1] ^ 1]))
        with patch.object(self.module.os, 'geteuid', return_value=0), \
                patch.object(self.module, 'api') as api, patch.object(self.module, 'run') as run:
            with self.assertRaisesRegex(RuntimeError, 'integrity mismatch'):
                self.module.main()
            api.assert_not_called()
            run.assert_not_called()

    def test_broken_pack_symlink_fails_before_docker(self):
        shutil.rmtree(self.pack)
        self.pack.symlink_to(Path(self.temp.name) / 'missing', target_is_directory=True)
        with patch.object(self.module.os, 'geteuid', return_value=0), \
                patch.object(self.module, 'api') as api, patch.object(self.module, 'run') as run:
            with self.assertRaisesRegex(RuntimeError, 'real directory, not a symlink'):
                self.module.main()
            api.assert_not_called()
            run.assert_not_called()

    def test_valid_pack_reaches_existing_preflight_not_network(self):
        with patch.object(self.module.os, 'geteuid', return_value=0), \
                patch.object(self.module.shutil, 'which', return_value=None), \
                patch.object(self.module, 'api') as api:
            with self.assertRaisesRegex(RuntimeError, 'Required host program is missing'):
                self.module.main()
            api.assert_not_called()

    def test_no_pack_does_not_require_unused_optional_helper(self):
        shutil.rmtree(self.pack)
        (self.root / 'scripts/operator_themes.py').unlink()
        with patch.object(self.module.os, 'geteuid', return_value=0), \
                patch.object(self.module.shutil, 'which', return_value=None), \
                patch.object(self.module, 'api') as api:
            with self.assertRaisesRegex(RuntimeError, 'Required host program is missing'):
                self.module.main()
            api.assert_not_called()

    def test_transaction_suite_all_four_source_layouts(self):
        # Reproduce the exact user invocation, with and without private pack / Git.
        (self.root / 'tests').mkdir()
        shutil.copy2(ROOT / 'tests/deploy-regression.py', self.root / 'tests/deploy-regression.py')
        expected = ['success', 'readiness_failure', 'cleanup_failure', 'lost_candidate_create',
                    'lost_rename', 'lost_replacement_create', 'offline_audit_failure', 'live_audit_failure']
        env = {k: v for k, v in os.environ.items() if k != 'PYTHONPATH' and not k.startswith('GIT_')}
        env['PYTHONDONTWRITEBYTECODE'] = '1'
        for with_git in (False, True):
            if with_git:
                subprocess.run(['git', 'init', '--quiet', str(self.root)], check=True,
                               env=env, capture_output=True, timeout=15)
            for with_pack in (True, False):
                with self.subTest(git=with_git, pack=with_pack):
                    if with_pack and not self.pack.exists():
                        self.pack, self.manifest = make_pack(self.root)
                    if not with_pack and self.pack.exists():
                        shutil.rmtree(self.pack)
                    result = subprocess.run([sys.executable, '-I', '-B',
                                             str(self.root / 'tests/deploy-regression.py')],
                                            cwd=Path(self.temp.name), env=env,
                                            capture_output=True, text=True, timeout=40)
                    self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
                    for mode in expected:
                        self.assertIn('PASS: deployment transaction ' + mode, result.stdout)
                    self.assertEqual((self.root / '.git').exists(), with_git)
        self.assertFalse((self.root / '__pycache__').exists())


if __name__ == '__main__':
    unittest.main(verbosity=2)
