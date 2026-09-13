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
            if 'sh' in args and '/run/securitysearch-php-runtime' in args[-1]:
                return 'fpm'
            url = args[-1]
            if url == 'http://127.0.0.1/' and '-fsSI' in args:
                return "Content-Security-Policy: script-src 'none'; connect-src 'none'\nX-SecuritySearch-Render: static-home\nCache-Control: public, max-age=60, stale-while-revalidate=30\nVary: Cookie, Authorization"
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
        self.assertEqual(len(calls), 8)
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



class NativeFixtureIsolation(unittest.TestCase):
    """Real generator and filesystem checks; no native Apache success is implied."""
    def setUp(self):
        from native_config_fixture import preserve_source_config
        self.guard = preserve_source_config
        self.temp = tempfile.TemporaryDirectory(prefix='ss-native-config-fixture-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        for name in ('data/config.php', 'docker/gen_config.php'):
            dest = self.root / name
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(ROOT / name, dest)
        (self.root / 'data/captcha').mkdir()
        self.config = self.root / 'data/config.php'
        self.config.chmod(0o640)
        self.original = self.config.read_bytes()

    def generate(self):
        php(self.root, 'docker/gen_config.php')
        self.assertNotEqual(self.config.read_bytes(), self.original)
        self.assertIn(b'Generated by docker/gen_config.php', self.config.read_bytes())

    def test_generator_runs_and_exact_source_is_restored(self):
        with self.guard(self.root):
            self.generate()
        self.assertEqual(self.config.read_bytes(), self.original)
        self.assertEqual(self.config.stat().st_mode & 0o777, 0o640)

    def test_fixture_assertion_is_not_hidden_and_source_is_restored(self):
        with self.assertRaisesRegex(AssertionError, 'fixture failed'):
            with self.guard(self.root):
                self.generate()
                raise AssertionError('fixture failed')
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_system_exit_is_not_hidden_and_source_is_restored(self):
        with self.assertRaises(SystemExit):
            with self.guard(self.root):
                self.generate()
                raise SystemExit(2)
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_native_entrypoint_wrapper_uses_guard_on_failure(self):
        spec = importlib.util.spec_from_file_location('native_home_fixture', ROOT / 'tests/static-home-http-regression.py')
        mod = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(mod)
        def failed_native_run():
            self.generate()
            raise RuntimeError('native fixture failed')
        with patch.object(mod, 'ROOT', self.root), patch.object(mod, 'run_native', failed_native_run):
            with self.assertRaisesRegex(RuntimeError, 'native fixture failed'):
                mod.main()
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_partial_writes_restore_all_original_bytes(self):
        import native_config_fixture as fixture
        write = os.write
        def short_write(fd, data):
            return write(fd, data[:max(1, len(data) // 3)])
        with patch.object(fixture.os, 'write', short_write):
            with self.guard(self.root):
                self.generate()
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_storage_failure_is_not_promoted_to_success(self):
        import native_config_fixture as fixture
        with patch.object(fixture.os, 'fsync', side_effect=OSError('injected fsync failure')):
            with self.assertRaisesRegex(OSError, 'injected fsync'):
                with self.guard(self.root):
                    self.generate()

    def test_symlink_is_refused_without_touching_target(self):
        target = self.root / 'outside.php'
        target.write_bytes(self.original)
        self.config.unlink()
        self.config.symlink_to(target)
        with self.assertRaises(RuntimeError):
            with self.guard(self.root):
                self.fail('symlink was accepted')
        self.assertEqual(target.read_bytes(), self.original)

    def test_hardlink_is_refused(self):
        os.link(self.config, self.root / 'linked.php')
        with self.assertRaises(RuntimeError):
            with self.guard(self.root):
                self.fail('hardlink was accepted')

    def test_replaced_path_is_not_overwritten(self):
        with self.assertRaisesRegex(RuntimeError, 'replaced configuration'):
            with self.guard(self.root):
                self.config.unlink()
                self.config.write_bytes(b'foreign replacement')
        self.assertEqual(self.config.read_bytes(), b'foreign replacement')

    def test_other_source_files_are_not_restored_or_exempted(self):
        other = self.root / 'visitor.php'
        other.write_text('before')
        with self.guard(self.root):
            self.generate()
            other.write_text('unexpected change')
        self.assertEqual(other.read_text(), 'unexpected change')
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_no_change_is_a_noop_for_content(self):
        with self.guard(self.root):
            self.assertEqual(self.config.read_bytes(), self.original)
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_oversized_generated_config_is_restored(self):
        import native_config_fixture as fixture
        with self.guard(self.root):
            self.config.write_bytes(b'x' * (fixture.MAX_CONFIG_BYTES + 1))
        self.assertEqual(self.config.read_bytes(), self.original)
        self.assertEqual(self.config.stat().st_mode & 0o777, 0o640)

    def test_oversized_output_does_not_mask_original_assertion(self):
        import native_config_fixture as fixture
        failure = AssertionError('original native fixture failure')
        with self.assertRaises(AssertionError) as raised:
            with self.guard(self.root):
                self.config.write_bytes(b'x' * (fixture.MAX_CONFIG_BYTES + 1))
                raise failure
        self.assertIs(raised.exception, failure)
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_large_sparse_generated_output_has_bounded_comparison_reads(self):
        # A small original prefix followed by sparse bytes must not be read in full.
        import native_config_fixture as fixture
        real_read = os.read
        observed = []
        def measured_read(fd, size):
            raw = real_read(fd, size)
            observed.append(len(raw))
            return raw
        measure = patch.object(fixture.os, 'read', measured_read)
        try:
            with self.guard(self.root):
                with self.config.open('wb') as output:
                    output.write(self.original)
                    output.truncate(64 * 1024 * 1024)
                measure.start()
            self.assertLessEqual(sum(observed), 2 * len(self.original) + 2)
        finally:
            measure.stop()
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_restoration_handles_short_reads(self):
        import native_config_fixture as fixture
        real_read = os.read
        def short_read(fd, size):
            return real_read(fd, min(size, 17))
        with patch.object(fixture.os, 'read', short_read):
            with self.guard(self.root):
                self.config.write_bytes(b'x' * len(self.original))
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_same_size_late_mismatch_is_restored(self):
        with self.guard(self.root):
            self.config.write_bytes(self.original[:-1] + b'X')
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_original_prefix_with_trailing_bytes_is_not_accepted(self):
        with self.guard(self.root):
            self.config.write_bytes(self.original + b'X')
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_empty_or_truncated_generated_output_is_restored(self):
        for output in (b'', self.original[:19]):
            with self.subTest(length=len(output)):
                with self.guard(self.root):
                    self.config.write_bytes(output)
                self.assertEqual(self.config.read_bytes(), self.original)

    def test_initial_oversized_source_is_still_refused_without_mutation(self):
        import native_config_fixture as fixture
        oversized = b'x' * (fixture.MAX_CONFIG_BYTES + 1)
        self.config.write_bytes(oversized)
        with self.assertRaisesRegex(RuntimeError, 'Unsafe native fixture'):
            with self.guard(self.root):
                self.fail('oversized original source was accepted')
        self.assertEqual(self.config.read_bytes(), oversized)

    def test_keyboard_interrupt_is_not_hidden_and_source_is_restored(self):
        failure = KeyboardInterrupt('native fixture interrupted')
        with self.assertRaises(KeyboardInterrupt) as raised:
            with self.guard(self.root):
                self.config.write_bytes(b'partial generation')
                raise failure
        self.assertIs(raised.exception, failure)
        self.assertEqual(self.config.read_bytes(), self.original)

    def test_unchanged_content_never_invokes_a_content_write(self):
        import native_config_fixture as fixture
        with patch.object(fixture.os, 'write', side_effect=AssertionError('unexpected write')):
            with self.guard(self.root):
                pass
        self.assertEqual(self.config.read_bytes(), self.original)

if __name__ == '__main__':
    unittest.main(verbosity=2)
