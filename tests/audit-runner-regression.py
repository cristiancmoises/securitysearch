#!/usr/bin/env python3
"""Exercise the real shell runner with small fixtures, never Docker or the network."""
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
RUNNER = (ROOT / 'scripts/test.sh').read_text()
PREFIX, REST = RUNNER.split('# BEGIN SUITES\n', 1)
BODY, SUFFIX = REST.split('# END SUITES\n', 1)


class Runner(unittest.TestCase):
    def run_fixture(self, lines, *args):
        with tempfile.TemporaryDirectory() as temp:
            scripts = Path(temp) / 'scripts'
            scripts.mkdir()
            script = scripts / 'test.sh'
            script.write_text(PREFIX + '\n'.join(lines) + '\n' + SUFFIX)
            return subprocess.run(['sh', str(script), *args], capture_output=True,
                                  text=True, timeout=10)

    def test_collects_failures_and_runs_later_commands(self):
        result = self.run_fixture([
            "run_test sh -c 'echo FAIL_A; exit 3'",
            "run_test sh -c 'echo MIDDLE_PASS'",
            "run_test sh -c 'echo FAIL_B; exit 7'",
            "run_test sh -c 'echo FINAL_PASS'",
        ], '--keep-going')
        self.assertEqual(result.returncode, 1)
        self.assertIn('FINAL_PASS\n', result.stdout)
        self.assertIn('Commands passed: 2; failed: 2.', result.stdout)
        self.assertIn('FAIL (exit 3)', result.stderr)
        self.assertIn('FAIL (exit 7)', result.stderr)
        self.assertIn('deployment must not continue', result.stderr)

    def test_default_still_fails_fast(self):
        result = self.run_fixture(["run_test sh -c 'exit 9'",
                                   "run_test sh -c 'echo MUST_NOT_RUN'"])
        self.assertEqual(result.returncode, 9)
        self.assertNotIn('MUST_NOT_RUN', result.stdout)

    def test_missing_program_is_failure_not_skip(self):
        result = self.run_fixture(['run_test securitysearch-nonexistent-test-program',
                                   "run_test sh -c 'echo LATER'"], '--keep-going')
        self.assertEqual(result.returncode, 1)
        self.assertIn('FAIL (exit 127)', result.stderr)
        self.assertIn('LATER\n', result.stdout)

    def test_success_and_argument_boundaries(self):
        result = self.run_fixture(["run_test sh -c 'test \"$1\" = \"two words\"' sh 'two words'"],
                                  '--keep-going')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('Commands passed: 1; failed: 0.', result.stdout)

    def test_unknown_arguments_refused(self):
        for args in [('--skip-tests',), ('--keep-going', 'extra')]:
            with self.subTest(args=args):
                result = self.run_fixture(["run_test sh -c 'echo MUST_NOT_RUN'"], *args)
                self.assertEqual(result.returncode, 2)
                self.assertNotIn('MUST_NOT_RUN', result.stdout)

    def test_real_suite_and_docker_acceptance_are_not_shortened(self):
        commands = [line for line in BODY.splitlines() if line.startswith('run_test ')]
        self.assertEqual(len(commands), 61)
        self.assertEqual(len(set(commands)), len(commands))
        for name in ['native-runtime.php', 'operator-archive-regression.py',
                     'deploy-import-regression.py', 'deploy-regression.py', 'http-regression.py',
                     'redlib-failover-regression.php', 'publication-v0.9.22-regression.py',
                     'news-primary-regression.php','news-deploy-regression.py',
                     'picture-http-regression.py','publication-v0.9.23-regression.py',
                     'news-audit-contract-regression.py']:
            self.assertTrue(any(name in command for command in commands), name)
        deploy = (ROOT / 'scripts/deploy-ionos.py').read_text()
        self.assertIn('sh scripts/test.sh --keep-going', deploy)
        self.assertIn('if result.returncode != 0 or code != 0:', deploy)
        self.assertIn("'--network','none'", deploy)


if __name__ == '__main__':
    unittest.main(verbosity=2)
