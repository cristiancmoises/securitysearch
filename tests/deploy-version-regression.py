#!/usr/bin/env python3
"""Source-derived version gate regression. Real PHP; Docker/HTTP are offline fixtures.

The expected marker is never copied into a positive mock: it comes from the
shipped PHP configuration, including the actual Docker configuration generator.
No Git metadata, native search extensions, provider requests or Docker are needed.
"""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location('version_gate_deployer', ROOT / 'scripts/deploy-ionos.py')
DEPLOY = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(DEPLOY)
PHP = shutil.which('php')


def environment():
    return {k: v for k, v in os.environ.items()
            if not k.startswith(('FOURGET_', 'GIT_', 'SECURITYSEARCH_'))
            and k not in ('PHPRC', 'PHP_INI_SCAN_DIR', 'PYTHONPATH')}


def php(root, *args):
    if PHP is None:
        raise RuntimeError('PHP is required for the source-derived readiness regression.')
    result = subprocess.run([PHP, '-n', *args], cwd=root, env=environment(),
                            text=True, capture_output=True, timeout=15, check=True)
    return result.stdout


def source_marker(root):
    return php(root, '-r', 'require "data/config.php"; echo config::VERSION."|".config::DEFAULT_THEME;')


class SourceIdentity(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='securitysearch-version-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name) / 'source-without-git'
        for name in ('data/config.php', 'data/release-version.txt', 'docker/gen_config.php'):
            dest = self.root / name
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(ROOT / name, dest)
        self.original = source_marker(self.root)
        self.asset, self.theme = self.original.split('|')

    def generate(self, **settings):
        result = subprocess.run([PHP, '-n', 'docker/gen_config.php'], cwd=self.root,
                                env={**environment(), **settings}, capture_output=True,
                                text=True, timeout=15)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertFalse((self.root / '.git').exists())
        return source_marker(self.root)

    def run_readiness(self, marker=None, failure=None):
        calls = []
        # This is transport scaffolding, not evidence of a live HTTP request.
        def run(*args, capture=False):
            calls.append(args)
            self.assertEqual(args[:2], ('docker', 'exec'))
            if 'php' in args and 'echo config::VERSION' in args[-1]:
                return marker if marker is not None else php(self.root, '-r', args[-1])
            if 'php' in args:
                return 'missing' if failure == 'adapters' else 'ready'
            url = args[-1]
            if url == 'http://127.0.0.1/' and '-fsSI' in args:
                return "Content-Security-Policy: script-src 'none'; connect-src 'none'"
            if url == 'http://127.0.0.1/':
                return ('In Code We Trust. zupt-web.securityops.co ' +
                        ''.join('<style data-home-style="' + n + '"></style>' for n in ('base', 'black', 'controls')))
            if url == 'http://127.0.0.1/images':
                return "script-src 'self'; connect-src 'self'"
            if '/static/images-infinite.js?' in url:
                return 'IntersectionObserver createDocumentFragment'
            if '/static/images-motion.js?' in url:
                return 'MutationObserver MAX_PLAYING'
            raise AssertionError('Unexpected request in readiness fixture')
        with patch.object(DEPLOY, 'inspect', return_value={'State': {
                'Status': 'running', 'Health': {'Status': 'healthy'}}}), \
                patch.object(DEPLOY, 'run', side_effect=run):
            DEPLOY.healthy('offline-candidate')
        return calls

    def test_expected_release_and_asset_match_actual_php_source(self):
        self.assertEqual(DEPLOY.VERSION, (self.root / 'data/release-version.txt').read_text().strip())
        self.assertIs(type(DEPLOY.ASSET_VERSION), int)
        self.assertEqual(DEPLOY.ASSET_VERSION, int(self.asset))
        self.assertEqual(self.theme, 'Black')
        DEPLOY.validate_source_marker(self.original)

    def test_real_generated_current_config_passes_entire_gate(self):
        # An old ambient version override must NOT become the expected version.
        marker = self.generate(FOURGET_VERSION=str(int(self.asset) - 1), FOURGET_DEFAULT_THEME='Black')
        self.assertEqual(marker, self.original)
        calls = self.run_readiness()
        self.assertEqual(len(calls), 7)
        urls = [call[-1] for call in calls if 'curl' in call]
        self.assertIn('http://127.0.0.1/static/images-infinite.js?v' + self.asset, urls)
        self.assertIn('http://127.0.0.1/static/images-motion.js?v' + self.asset, urls)

    def test_source_only_archive_and_generated_output_are_not_mutated_by_gate(self):
        self.generate(FOURGET_DEFAULT_THEME='Black')
        paths = [p for p in self.root.rglob('*') if p.is_file()]
        before = {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for p in paths}
        self.run_readiness()
        self.assertEqual(before, {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for p in paths})
        self.assertFalse((self.root / '.git').exists())

    def test_previous_and_future_versions_fail_before_http(self):
        for asset in (int(self.asset) - 1, int(self.asset) + 1):
            marker = str(asset) + '|Black'
            with self.subTest(asset=asset), patch.object(DEPLOY, 'inspect', return_value={
                    'State': {'Status': 'running', 'Health': {'Status': 'healthy'}}}), \
                    patch.object(DEPLOY, 'run', return_value=marker) as run:
                with self.assertRaisesRegex(RuntimeError, 'expected asset ' + self.asset):
                    DEPLOY.healthy('offline-candidate')
                self.assertEqual(run.call_count, 1)
                self.assertNotIn('curl', run.call_args.args)

    def test_real_generated_wrong_theme_is_not_accepted(self):
        marker = self.generate(FOURGET_DEFAULT_THEME='Lain')
        self.assertEqual(marker, self.asset + '|Lain')
        with self.assertRaisesRegex(RuntimeError, 'theme not Black'):
            self.run_readiness()

    def test_malformed_markers_are_rejected_without_reflecting_output(self):
        secret = 'PRIVATE_TOKEN_DO_NOT_PRINT'
        for marker in (None, b'31|Black', '', '31', '31|', '031|Black', True,
                       '31|Black|' + secret, '31|Black\n' + secret,
                       secret * 30, '31|' + secret):
            with self.subTest(marker_type=type(marker).__name__):
                with self.assertRaises(RuntimeError) as raised:
                    DEPLOY.validate_source_marker(marker)
                self.assertNotIn(secret, str(raised.exception))
                self.assertLess(len(str(raised.exception)), 350)

    def test_php_trailing_newline_is_accepted(self):
        DEPLOY.validate_source_marker(self.original + '\n')

    def test_old_mock_literal_cannot_pass_as_current_config(self):
        self.assertNotEqual(self.original, '30|Black')
        with self.assertRaisesRegex(RuntimeError, 'observed asset 30'):
            DEPLOY.validate_source_marker('30|Black')

    def test_good_version_does_not_bypass_later_helper_checks(self):
        with self.assertRaisesRegex(RuntimeError, 'provider/media helpers'):
            self.run_readiness(failure='adapters')

    def test_compilation_order_and_no_config_mutation_in_gate(self):
        entry = (ROOT / 'docker/docker-entrypoint.sh').read_text()
        self.assertLess(entry.index('php ./docker/gen_config.php'), entry.index('php ./lib/build_view_resources.php --build'))
        self.assertLess(entry.index('php ./lib/build_view_resources.php --build'), entry.index('exec httpd'))
        source = (ROOT / 'scripts/deploy-ionos.py').read_text()
        self.assertIn('env.pop(\'FOURGET_VERSION\',None)', source)
        self.assertIn('if masks_runtime or (masks_app and not safe_data):', source)
        self.assertIn('healthy(candidate)', source)
        self.assertIn('healthy(replacement)', source)


if __name__ == '__main__':
    unittest.main(verbosity=2)
