#!/usr/bin/env python3
"""Bounded POSIX process capture into a new private log; never buffered without a limit.

The caller owns cleanup of any Docker container started by the captured CLI.
Terminating a CLI process alone does not guarantee the container has stopped.
"""
import hashlib
import math
import os
from pathlib import Path
import selectors
import signal
import stat
import subprocess
import time

MAX_BYTES = 8 * 1024 * 1024
MAX_SECONDS = 1800

class CaptureError(RuntimeError):
    pass


def directory_identity(directory):
    directory = Path(directory).absolute()
    for part in reversed((directory, *directory.parents)):
        info = part.lstat()
        if not stat.S_ISDIR(info.st_mode):
            raise CaptureError('audit_directory_not_real')
    return info.st_dev, info.st_ino


def _stop_process_group(process):
    """Reap the child and terminate pipe-holding descendants in our new session."""
    try:
        os.killpg(process.pid, signal.SIGTERM)
    except ProcessLookupError:
        pass
    try:
        process.wait(timeout=1)
    except subprocess.TimeoutExpired:
        pass
    try:
        os.killpg(process.pid, signal.SIGKILL)
    except ProcessLookupError:
        pass
    process.wait(timeout=3)


def capture(argv, directory, *, timeout=MAX_SECONDS, max_bytes=MAX_BYTES):
    """Return (execution metadata, bounded raw bytes), preserving output on failure.

    Smaller limits are supported for offline regression tests. Raising the
    deployed limits is not accepted. An existing log is never overwritten.
    """
    if (not isinstance(argv, (list, tuple)) or not argv or any(not isinstance(a, str) for a in argv)
            or type(max_bytes) is not int or not 1 <= max_bytes <= MAX_BYTES
            or type(timeout) not in (int, float) or not math.isfinite(timeout)
            or not 0 < timeout <= MAX_SECONDS):
        raise CaptureError('invalid_capture_arguments')
    directory = Path(directory).absolute()
    identity = directory_identity(directory)
    directory_fd = os.open(directory, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    process = None
    stopped = False
    log_fd = None
    selector = None
    started = time.monotonic()
    reason = 'completed'
    count = 0
    try:
        info = os.fstat(directory_fd)
        if (info.st_dev, info.st_ino) != identity:
            raise CaptureError('audit_directory_changed')
        log_fd = os.open('offline-audit.log', os.O_RDWR | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW,
                         0o600, dir_fd=directory_fd)
        os.fsync(directory_fd)
        try:
            process = subprocess.Popen(list(argv), stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                                       stderr=subprocess.STDOUT, start_new_session=True, bufsize=0)
        except OSError:
            reason = 'spawn_failed'
        if process is not None:
            selector = selectors.DefaultSelector()
            os.set_blocking(process.stdout.fileno(), False)
            selector.register(process.stdout, selectors.EVENT_READ)
            deadline = started + timeout
            eof = False
            while not eof:
                remaining = deadline - time.monotonic()
                if remaining <= 0:
                    reason = 'timeout'
                    break
                for key, _ in selector.select(min(0.2, remaining)):
                    try:
                        chunk = os.read(key.fd, min(16384, max_bytes - count + 1))
                    except BlockingIOError:
                        continue
                    if not chunk:
                        eof = True
                        break
                    allowed = min(len(chunk), max_bytes - count)
                    view = memoryview(chunk)[:allowed]
                    while view:
                        written = os.write(log_fd, view)
                        if written <= 0:
                            raise CaptureError('audit_log_write_failed')
                        count += written
                        view = view[written:]
                    if len(chunk) > allowed:
                        reason = 'output_limit'
                        eof = True
                        break
            if reason == 'completed':
                remaining = deadline - time.monotonic()
                try:
                    process.wait(timeout=max(0, remaining))
                except subprocess.TimeoutExpired:
                    reason = 'timeout'
            # Always kill our process group; the caller also removes its exact container.
            _stop_process_group(process)
            stopped = True
        os.fsync(log_fd)
        os.fsync(directory_fd)
        if directory_identity(directory) != identity:
            raise CaptureError('audit_directory_changed')
        entry = os.stat('offline-audit.log', dir_fd=directory_fd, follow_symlinks=False)
        opened = os.fstat(log_fd)
        if (not stat.S_ISREG(entry.st_mode) or (entry.st_dev, entry.st_ino) != (opened.st_dev, opened.st_ino)
                or opened.st_size != count):
            raise CaptureError('audit_log_replaced_or_changed')
        os.lseek(log_fd, 0, os.SEEK_SET)
        parts = []
        left = count
        while left:
            part = os.read(log_fd, min(65536, left))
            if not part:
                raise CaptureError('audit_log_short_read')
            parts.append(part)
            left -= len(part)
        raw = b''.join(parts)
        return ({'reason': reason, 'returncode': None if process is None else process.returncode,
                 'bytes': count, 'sha256': hashlib.sha256(raw).hexdigest(),
                 'elapsed_seconds': round(time.monotonic() - started, 3),
                 'byte_limit': max_bytes, 'seconds_limit': timeout}, raw)
    except OSError as error:
        raise CaptureError('audit_capture_io_error; errno=' + str(error.errno)) from None
    finally:
        if process is not None:
            if not stopped:
                _stop_process_group(process)
            if process.stdout is not None:
                process.stdout.close()
        if selector is not None:
            selector.close()
        try:
            if log_fd is not None:
                try:
                    os.fsync(log_fd)
                finally:
                    os.close(log_fd)
            os.fsync(directory_fd)
        finally:
            os.close(directory_fd)
