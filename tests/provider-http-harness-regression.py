#!/usr/bin/env python3
"""Exercise mock isolation without upstream traffic, with or without ext-curl.

The 15 predeclared-function combinations are userland fixtures, not a substitute
for an ext-curl-enabled runtime check. On that runtime we also check the native
functions explicitly. All PHP invocations are child CLI processes.
"""
import itertools
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
TARGET = ROOT / 'tests/provider-http-regression.php'
FUNCTIONS = ('curl_setopt', 'curl_exec', 'curl_share_init', 'curl_share_setopt')
PHP = shutil.which('php')
DISABLED = ','.join(FUNCTIONS)


def php(*args):
    return subprocess.run([PHP, *args], cwd=ROOT, capture_output=True, text=True, timeout=15)


class HarnessTests(unittest.TestCase):
    def test_plain_lint_with_default_runtime(self):
        result = php('-l', str(TARGET))
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_documented_isolated_command(self):
        result = php('-d', 'disable_functions=' + DISABLED, str(TARGET))
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('PASS: legacy transport caps', result.stdout)

    def test_all_predeclared_function_combinations_lint_and_refuse_execution(self):
        source = TARGET.read_text()
        for count in range(1, len(FUNCTIONS) + 1):
            for functions in itertools.combinations(FUNCTIONS, count):
                with self.subTest(predeclared=functions), tempfile.TemporaryDirectory() as directory:
                    # php -n avoids host extensions. A tripwire makes any call
                    # through these pre-existing functions a hard failure.
                    definitions = '\n'.join(
                        'function ' + name + '(...$args) {'
                        'fwrite(STDERR, "UNEXPECTED_TRANSPORT_CALL\\n"); exit(99);}'
                        for name in functions
                    )
                    path = Path(directory) / 'collision.php'
                    path.write_text('<?php\n' + definitions + '\n?>\n' + source)
                    lint = php('-n', '-l', str(path))
                    self.assertEqual(lint.returncode, 0, lint.stdout + lint.stderr)
                    result = php('-n', str(path))
                    self.assertEqual(result.returncode, 2, result.stdout + result.stderr)
                    self.assertIn('Offline cURL mocks are not isolated:', result.stderr)
                    self.assertNotIn('UNEXPECTED_TRANSPORT_CALL', result.stdout + result.stderr)
                    self.assertNotIn('Cannot redeclare', result.stdout + result.stderr)
                    self.assertNotIn('PASS:', result.stdout)

    def test_default_runtime_mode(self):
        probe = php('-d', 'disable_functions=', '-r',
                    'echo extension_loaded("curl") ? "native" : "absent";')
        self.assertEqual(probe.returncode, 0, probe.stderr)
        result = php('-d', 'disable_functions=', str(TARGET))
        if probe.stdout == 'native':
            self.assertEqual(result.returncode, 2, result.stdout + result.stderr)
            self.assertIn('Offline cURL mocks are not isolated:', result.stderr)
            print('PASS: native ext-curl stays enabled; unsafe test invocation refused.')
        else:
            self.assertEqual(probe.stdout, 'absent')
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            self.assertIn('PASS: legacy transport caps', result.stdout)
            print('INFO: ext-curl absent here; native-extension check remains a VPS gate.')


if __name__ == '__main__':
    if PHP is None:
        raise SystemExit('Required audit interpreter missing: php')
    unittest.main(verbosity=2)
