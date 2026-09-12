#!/usr/bin/env python3
"""Execute real local child processes; no Docker/provider/network access."""
import contextlib
import errno
import os
from pathlib import Path
import signal
import stat
import subprocess
import sys
import tempfile
import time
import unittest
from unittest.mock import patch
from audit_fixture import load

m = load('audit_stream')

class StreamTests(unittest.TestCase):
    def capture(self, code, **kw):
        with tempfile.TemporaryDirectory() as temp:
            report, raw = m.capture([sys.executable, '-S', '-c', code], Path(temp), **kw)
            self.assertEqual((Path(temp)/'offline-audit.log').read_bytes(), raw)
            self.assertEqual(stat.S_IMODE((Path(temp)/'offline-audit.log').stat().st_mode), 0o600)
            return report, raw

    def test_binary_streams_merge_in_order(self):
        report, raw = self.capture("import os;os.write(1,b'first\\n');os.write(2,b'second\\n');os.write(1,b'last\\xff')")
        self.assertEqual(raw, b'first\nsecond\nlast\xff')
        self.assertEqual(report['returncode'], 0)
        self.assertEqual(report['reason'], 'completed')

    def test_exit_status_and_empty_output(self):
        report, raw = self.capture('raise SystemExit(7)')
        self.assertEqual(raw, b''); self.assertEqual(report['returncode'], 7)
        self.assertEqual(report['reason'], 'completed')

    def test_chunked_output_is_complete(self):
        report, raw = self.capture("import os;[os.write(1,b'abc'*10000) for _ in range(20)]")
        self.assertEqual(raw, b'abc'*200000)
        self.assertEqual(report['bytes'], 600000)

    def test_exact_byte_limit_is_allowed(self):
        report, raw = self.capture("import os;os.write(1,b'x'*1024)", max_bytes=1024)
        self.assertEqual(report['reason'], 'completed'); self.assertEqual(len(raw), 1024)

    def test_one_byte_overflow_is_a_failure(self):
        report, raw = self.capture("import os;os.write(1,b'x'*1025)", max_bytes=1024)
        self.assertEqual(report['reason'], 'output_limit'); self.assertEqual(len(raw), 1024)

    def test_runaway_writer_is_killed_and_log_is_capped(self):
        report, raw = self.capture("import os\nwhile True:os.write(1,b'x'*65536)", max_bytes=8192, timeout=2)
        self.assertEqual(report['reason'], 'output_limit'); self.assertEqual(len(raw), 8192)
        self.assertLess(report['elapsed_seconds'], 5)

    def test_silent_timeout_retains_partial_log(self):
        report, raw = self.capture("import os,time;os.write(1,b'before timeout\\n');time.sleep(20)", timeout=0.5)
        self.assertEqual(report['reason'], 'timeout'); self.assertEqual(raw, b'before timeout\n')
        self.assertNotEqual(report['returncode'], 0)

    def test_timeout_after_child_closes_pipes(self):
        report, raw = self.capture('import os,time;os.close(1);os.close(2);time.sleep(20)', timeout=0.5)
        self.assertEqual(report['reason'], 'timeout')

    def test_descendant_holding_pipe_cannot_hang_capture(self):
        report, raw = self.capture("import os,time\npid=os.fork()\nif pid==0:time.sleep(20)\nelse:os._exit(0)", timeout=0.5)
        self.assertEqual(report['reason'], 'timeout')
        self.assertLess(report['elapsed_seconds'], 5)

    def test_ignored_sigterm_escalates(self):
        report, raw = self.capture('import signal,time;signal.signal(signal.SIGTERM,signal.SIG_IGN);time.sleep(20)', timeout=0.5)
        self.assertEqual(report['reason'], 'timeout'); self.assertEqual(report['returncode'], -signal.SIGKILL)
        self.assertLess(report['elapsed_seconds'], 5)

    def test_missing_program_is_not_success(self):
        with tempfile.TemporaryDirectory() as temp:
            report, raw = m.capture(['/nonexistent-securitysearch-program'], Path(temp))
            self.assertEqual(report['reason'], 'spawn_failed'); self.assertIsNone(report['returncode'])

    def test_existing_log_is_never_overwritten(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp); path = root/'offline-audit.log'; path.write_bytes(b'old evidence')
            with patch.object(m.subprocess, 'Popen') as popen, self.assertRaises(m.CaptureError):
                m.capture([sys.executable, '-S', '-c', 'pass'], root)
            popen.assert_not_called(); self.assertEqual(path.read_bytes(), b'old evidence')

    def test_symlink_log_and_directory_are_refused(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp); real = root/'real'; real.mkdir(); target=root/'target'; target.write_text('preserve')
            (real/'offline-audit.log').symlink_to(target)
            with self.assertRaises(m.CaptureError):m.capture([sys.executable, '-S', '-c', 'pass'],real)
            link=root/'link'; link.symlink_to(real, target_is_directory=True)
            with self.assertRaises(m.CaptureError):m.capture([sys.executable, '-S', '-c', 'pass'],link)
            self.assertEqual(target.read_text(),'preserve')

    def test_replaced_directory_is_not_certified(self):
        with tempfile.TemporaryDirectory() as temp:
            directory=Path(temp)/'output';directory.mkdir()
            code='import os;os.rename('+repr(str(directory))+','+repr(str(directory)+'.old')+');os.mkdir('+repr(str(directory))+');os.write(1,b"retained")'
            with self.assertRaises(m.CaptureError):m.capture([sys.executable,'-S','-c',code],directory)
            self.assertEqual((Path(str(directory)+'.old')/'offline-audit.log').read_bytes(),b'retained')

    def test_disk_full_is_not_certified(self):
        with tempfile.TemporaryDirectory() as temp, patch.object(m.os,'write',side_effect=OSError(errno.ENOSPC,'fixture body')):
            with self.assertRaises(m.CaptureError) as error:
                m.capture([sys.executable,'-S','-c','print("output",flush=True)'],Path(temp))
            self.assertNotIn('fixture body',str(error.exception))

    def test_invalid_limits_are_not_accepted(self):
        for kw in ({'max_bytes':True},{'max_bytes':0},{'max_bytes':m.MAX_BYTES+1},{'timeout':0},{'timeout':True},{'timeout':float('nan')},{'timeout':float('inf')},{'timeout':1801}):
            with tempfile.TemporaryDirectory() as temp, self.assertRaises(m.CaptureError):
                m.capture([sys.executable,'-S','-c','pass'],Path(temp),**kw)

if __name__ == '__main__':unittest.main(verbosity=2)
