#!/usr/bin/env python3
"""Strict completion evidence for the offline native audit; no subprocesses or network.

This validates a transcript of trusted checked-in suites. It is not attestation
against a compromised Docker daemon, root user, image, or deliberately lying test.
"""
import hashlib
import json
import re
from pathlib import Path

RELEASE = '0.9.42'
COMMAND_COUNT = 130
MAX_LOG = 8 * 1024 * 1024
SUMMARY = '=== OFFLINE AUDIT SUMMARY ==='
FINISH = 'Every required test command completed successfully.'

class EvidenceError(RuntimeError):
    """Only fixed diagnostic identifiers; never copy test bodies into errors."""


def validate_commands(commands):
    if (not isinstance(commands, list) or len(commands) != COMMAND_COUNT
            or any(not isinstance(c, str) or not re.fullmatch(r'[A-Za-z0-9_./=, -]{1,512}', c)
                   for c in commands) or len(set(commands)) != COMMAND_COUNT):
        raise EvidenceError('invalid_command_inventory')
    return commands


def inventory_digest(commands):
    validate_commands(commands)
    return hashlib.sha256(('\n'.join(commands) + '\n').encode('ascii')).hexdigest()


def source_commands(root):
    """Refuse drift between the checked-in inventory and the actual shell runner."""
    root = Path(root)
    raw = (root / 'data/audit-commands-0.9.42.json').read_bytes()
    if len(raw) > 256 * 1024:
        raise EvidenceError('inventory_size_limit')
    try:
        value = json.loads(raw)
        if (not isinstance(value, dict) or type(value.get('schema')) is not int
                or value['schema'] != 1 or value.get('release') != RELEASE):
            raise EvidenceError('inventory_identity_mismatch')
        commands = validate_commands(value.get('commands'))
        script = (root / 'scripts/test.sh').read_text()
        if script.count('# BEGIN SUITES\n') != 1 or script.count('# END SUITES\n') != 1:
            raise EvidenceError('suite_boundaries_invalid')
        body = script.split('# BEGIN SUITES\n', 1)[1].split('# END SUITES\n', 1)[0]
        actual = [line[len('run_test '):] for line in body.splitlines() if line.startswith('run_test ')]
        if actual != commands:
            raise EvidenceError('runner_inventory_mismatch')
    except (ValueError, UnicodeError, KeyError, TypeError):
        raise EvidenceError('invalid_inventory') from None
    return commands


def validate_log(raw, commands):
    """Require every exact command once, in order, plus one final zero-failure summary."""
    validate_commands(commands)
    if not isinstance(raw, bytes) or not raw or len(raw) > MAX_LOG:
        raise EvidenceError('log_empty_or_oversized')
    try:
        text = raw.decode('utf-8', errors='strict')
    except UnicodeError:
        raise EvidenceError('invalid_log_encoding') from None
    lines = text.split('\n')
    observed = []
    for line in lines:
        if line.startswith('=== OFFLINE TEST'):
            match = re.fullmatch(r'=== OFFLINE TEST: (.+) ===', line)
            if match is None:
                raise EvidenceError('malformed_command_header')
            observed.append(match[1])
        if re.match(r'^(?:FAILED \(exit |[ \t]*FAIL \(exit |OFFLINE AUDIT FAILED:)', line):
            raise EvidenceError('failure_marker_present')
    if observed != commands:
        raise EvidenceError('command_sequence_incomplete_or_changed')
    if lines.count(SUMMARY) != 1:
        raise EvidenceError('missing_or_duplicate_summary')
    suffix = '\n' + SUMMARY + '\nCommands passed: ' + str(COMMAND_COUNT) + '; failed: 0.\n' + FINISH + '\n'
    if not text.endswith(suffix) or lines.count(FINISH) != 1:
        raise EvidenceError('final_success_summary_invalid')
    # A complete ordered command sequence must occur before the single summary.
    if text.rfind('=== OFFLINE TEST: ') > text.index(SUMMARY):
        raise EvidenceError('commands_after_summary')
    return {'commands': COMMAND_COUNT, 'inventory_sha256': inventory_digest(commands),
            'log_bytes': len(raw), 'log_sha256': hashlib.sha256(raw).hexdigest()}
