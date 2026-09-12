"""Explicitly synthetic audit transcripts; never deployment/production evidence."""
import hashlib
import importlib.util
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def load(name):
    spec = importlib.util.spec_from_file_location(name.replace('-', '_'), ROOT / 'scripts' / (name + '.py'))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def transcript(commands=None):
    if commands is None:
        commands = load('audit_evidence').source_commands(ROOT)
    return (''.join('\n=== OFFLINE TEST: ' + command + ' ===\nfixture success\n' for command in commands)
            + '\n=== OFFLINE AUDIT SUMMARY ===\nCommands passed: ' + str(len(commands))
            + '; failed: 0.\nEvery required test command completed successfully.\n').encode()


def captured(raw=None, *, returncode=0, reason='completed'):
    raw = transcript() if raw is None else raw
    def capture(argv, directory):
        (Path(directory) / 'offline-audit.log').write_bytes(raw)
        return ({'reason': reason, 'returncode': returncode, 'bytes': len(raw),
                 'sha256': hashlib.sha256(raw).hexdigest(), 'byte_limit': 8 * 1024 * 1024,
                 'seconds_limit': 1800, 'elapsed_seconds': 0.01}, raw)
    return capture

EXITED = {'State': {'ExitCode': 0, 'Running': False, 'Status': 'exited'}}
