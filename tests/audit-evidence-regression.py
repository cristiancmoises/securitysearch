#!/usr/bin/env python3
"""Reject incomplete/contradictory native transcripts; Docker calls are fixtures."""
import contextlib
import copy
import hashlib
import io
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
from audit_fixture import ROOT, load, transcript, captured, EXITED

m = load('audit_evidence')
d = load('deploy-ionos')
COMMANDS = m.source_commands(ROOT)

class EvidenceTests(unittest.TestCase):
    def test_exact_ordered_transcript(self):
        raw = transcript()
        report = m.validate_log(raw, COMMANDS)
        self.assertEqual(report['commands'], 119)
        self.assertEqual(report['log_sha256'], hashlib.sha256(raw).hexdigest())
        self.assertEqual(report['log_bytes'], len(raw))

    def test_empty_or_old_false_success_cases(self):
        for raw in (b'', b'fixture exited early\n', b'=== OFFLINE AUDIT SUMMARY ===\nCommands passed: 1; failed: 0.\nEvery required test command completed successfully.\n'):
            with self.subTest(raw=raw), self.assertRaises(m.EvidenceError):
                m.validate_log(raw, COMMANDS)

    def test_missing_duplicate_extra_and_reordered_commands(self):
        variants = [COMMANDS[:-1], COMMANDS + [COMMANDS[0]], [COMMANDS[1], COMMANDS[0]] + COMMANDS[2:],
                    [COMMANDS[0]] + COMMANDS[:-1], ['python3 tests/unknown.py'] + COMMANDS[1:]]
        for commands in variants:
            with self.subTest(length=len(commands)), self.assertRaises(m.EvidenceError):
                m.validate_log(transcript(commands), COMMANDS)

    def test_summary_alone_cannot_certify(self):
        raw = b'\n=== OFFLINE AUDIT SUMMARY ===\nCommands passed: 119; failed: 0.\nEvery required test command completed successfully.\n'
        with self.assertRaises(m.EvidenceError):
            m.validate_log(raw, COMMANDS)

    def test_duplicate_summary_and_trailing_success_spoof(self):
        good = transcript()
        for raw in (good + good, good + b'after final summary\n', good + good[good.index(b'\n=== OFFLINE AUDIT SUMMARY'):],
                    good.replace(b'Commands passed: 119;', b'Commands passed: 118;'), good.replace(b'failed: 0.', b'failed: 1.')):
            with self.subTest(length=len(raw)), self.assertRaises(m.EvidenceError):
                m.validate_log(raw, COMMANDS)

    def test_missing_final_marker_and_truncated_stream(self):
        for raw in (transcript()[:-1], transcript().replace((m.FINISH + '\n').encode(), b''), transcript()[:100]):
            with self.assertRaises(m.EvidenceError):
                m.validate_log(raw, COMMANDS)

    def test_failed_command_hidden_before_valid_summary(self):
        for marker in (b'FAILED (exit 1): secret-body\n', b'  FAIL (exit 3): secret-body\n', b'OFFLINE AUDIT FAILED: secret-body\n'):
            with self.assertRaises(m.EvidenceError) as result:
                m.validate_log(marker + transcript(), COMMANDS)
            self.assertNotIn('secret-body', str(result.exception))

    def test_malformed_header_is_not_ignored(self):
        with self.assertRaises(m.EvidenceError):
            m.validate_log(b'=== OFFLINE TEST broken header\n' + transcript(), COMMANDS)

    def test_invalid_encoding_size_type(self):
        for raw in (transcript() + b'\xff', 'not bytes', b'x' * (m.MAX_LOG + 1)):
            with self.assertRaises(m.EvidenceError):
                m.validate_log(raw, COMMANDS)

    def test_inventory_validation(self):
        for commands in (None, COMMANDS[:-1], [True] + COMMANDS[1:], ['injected\ncommand'] + COMMANDS[1:],
                         [COMMANDS[1]] + COMMANDS[1:]):
            with self.assertRaises(m.EvidenceError):
                m.validate_commands(commands)

    def test_source_manifest_and_runner_must_agree(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            (root/'data').mkdir(); (root/'scripts').mkdir()
            (root/'data/audit-commands-0.9.40.json').write_bytes((ROOT/'data/audit-commands-0.9.40.json').read_bytes())
            script = (ROOT/'scripts/test.sh').read_text()
            (root/'scripts/test.sh').write_text(script)
            self.assertEqual(m.source_commands(root), COMMANDS)
            (root/'scripts/test.sh').write_text(script.replace('run_test ' + COMMANDS[0], 'run_test python3 missing.py', 1))
            with self.assertRaises(m.EvidenceError):
                m.source_commands(root)

    def test_explicit_checks_survive_python_optimization(self):
        code = ('import sys;sys.path.insert(0,' + repr(str(ROOT/'scripts')) + ');'
                'import audit_evidence as m;'
                'commands=m.source_commands(' + repr(str(ROOT)) + ');'
                '\ntry:m.validate_log(b"",commands)\nexcept m.EvidenceError:sys.exit(0)\nsys.exit(91)')
        result = subprocess.run([sys.executable, '-O', '-c', code], capture_output=True, timeout=10)
        self.assertEqual(result.returncode, 0, result.stderr)

class DeploymentEvidenceTests(unittest.TestCase):
    def run_audit(self, raw=None, state=None, reason='completed', returncode=0):
        calls = []
        def run(*args, capture=False):
            calls.append(args)
            return 'a' * 64 if args[:2] == ('docker', 'create') else ''
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            with patch.object(d, 'run', side_effect=run), patch.object(d, 'inspect', return_value=state or EXITED), \
                 patch.object(d, 'audit_capture', side_effect=captured(raw, reason=reason, returncode=returncode)), \
                 contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
                try:
                    d.offline_audit('fixture-image', directory)
                    error = None
                except RuntimeError as failure:
                    error = failure
            path = directory/'offline-audit-result.json'
            evidence = json.loads(path.read_text()) if path.exists() else None
            self.assertEqual(calls[-1], ('docker', 'rm', '--force', 'a'*64))
            create = next(row for row in calls if row[:2] == ('docker', 'create'))
            self.assertEqual(create[2:6], ('--network', 'none', '--log-driver', 'none'))
            self.assertNotIn('-v', create); self.assertNotIn('-e', create)
            return error, evidence

    def test_complete_native_fixture_passes(self):
        error, report = self.run_audit()
        self.assertIsNone(error)
        self.assertEqual(report['proof']['commands'], 119)

    def test_zero_exits_with_no_commands_are_refused(self):
        for raw in (b'', b'early exit\n', b'=== OFFLINE AUDIT SUMMARY ===\nCommands passed: 119; failed: 0.\n'):
            error, report = self.run_audit(raw)
            self.assertIsNotNone(error)
            self.assertEqual(report['status'], 'failed')

    def test_either_exit_code_or_running_state_blocks(self):
        for status in ({'ExitCode': 1, 'Running': False, 'Status': 'exited'},
                       {'ExitCode': False, 'Running': False, 'Status': 'exited'},
                       {'ExitCode': 0, 'Running': True, 'Status': 'running'}, {'ExitCode': 0}):
            error, _ = self.run_audit(state={'State': status})
            self.assertIsNotNone(error)
        for returncode in (1, -15, False, None):
            error, _ = self.run_audit(returncode=returncode)
            self.assertIsNotNone(error)

    def test_timeout_or_overflow_cannot_pass_even_with_full_log(self):
        for reason in ('timeout', 'output_limit', 'spawn_failed'):
            error, report = self.run_audit(reason=reason)
            self.assertIsNotNone(error)
            self.assertEqual(report['capture']['reason'], reason)

    def test_failed_evidence_persistence_cannot_pass(self):
        records = load('deployment_state')
        real_loader = d.deployment_module
        def loader(name):
            return records if name == 'deployment_state' else real_loader(name)
        with patch.object(records, 'write_record', side_effect=records.StateError('fixture storage failure')), \
             patch.object(d, 'deployment_module', side_effect=loader):
            error, report = self.run_audit()
            self.assertIsInstance(error, records.StateError)
            self.assertIsNone(report)

if __name__ == '__main__':
    unittest.main(verbosity=2)
