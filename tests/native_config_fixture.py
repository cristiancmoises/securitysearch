"""Restore the source config after a native entrypoint fixture, not in production.

The real generator runs against the real source file during the fixture. Keep an
open descriptor to that exact regular file; never follow or overwrite a replacement.
Only config.php is restored. Other runtime changes remain visible to hash contracts.
"""
from contextlib import contextmanager
import os
from pathlib import Path
import stat

MAX_CONFIG_BYTES = 1024 * 1024


def _identity(st):
    return st.st_dev, st.st_ino


def _regular(st):
    return stat.S_ISREG(st.st_mode) and st.st_nlink == 1


def _read(fd):
    os.lseek(fd, 0, os.SEEK_SET)
    raw = bytearray()
    while len(raw) <= MAX_CONFIG_BYTES:
        part = os.read(fd, min(65536, MAX_CONFIG_BYTES + 1 - len(raw)))
        if not part:
            return bytes(raw)
        raw.extend(part)
    raise RuntimeError('Native fixture configuration exceeds the size limit.')


def _matches(fd, expected):
    """Compare at most captured length plus one byte, not generated-file size.

    Source capture is already capped at MAX_CONFIG_BYTES. The generator may leave
    a larger file, even after failing; that must not prevent safe restoration of
    the original. Short reads are valid and trailing bytes are a mismatch.
    """
    os.lseek(fd, 0, os.SEEK_SET)
    offset = 0
    while offset < len(expected):
        part = os.read(fd, min(65536, len(expected) - offset))
        if not part or part != expected[offset:offset + len(part)]:
            return False
        offset += len(part)
    return os.read(fd, 1) == b''


@contextmanager
def preserve_source_config(root):
    """Preserve exact source bytes/mode after success or a caught fixture failure.

    A hard termination cannot run cleanup; the disposable native audit must then
    fail its execution gate. A replaced, linked, or otherwise unsafe path is an
    error, not permission to overwrite another file or relax a hash expectation.
    """
    path = Path(root) / 'data/config.php'
    before = path.lstat()
    if not _regular(before) or not 0 < before.st_size <= MAX_CONFIG_BYTES:
        raise RuntimeError('Unsafe native fixture configuration.')
    fd = os.open(path, os.O_RDWR | os.O_NOFOLLOW | os.O_NONBLOCK)
    try:
        opened = os.fstat(fd)
        if not _regular(opened) or _identity(opened) != _identity(before):
            raise RuntimeError('Native fixture configuration changed before capture.')
        original = _read(fd)
        if len(original) != before.st_size:
            raise RuntimeError('Native fixture configuration changed during capture.')
        try:
            yield
        finally:
            current = path.lstat()
            held = os.fstat(fd)
            if (not _regular(current) or not _regular(held) or
                    _identity(current) != _identity(opened) or
                    _identity(held) != _identity(opened)):
                raise RuntimeError('Native fixture replaced configuration; refusing restoration.')
            if not _matches(fd, original):
                os.lseek(fd, 0, os.SEEK_SET)
                view = memoryview(original)
                while view:
                    count = os.write(fd, view)
                    if count <= 0:
                        raise OSError('Short native fixture configuration write.')
                    view = view[count:]
                os.ftruncate(fd, len(original))
                os.fsync(fd)
            # The fixture must not grant additional permissions to configuration.
            os.fchmod(fd, stat.S_IMODE(before.st_mode))
            if not _matches(fd, original) or _identity(path.lstat()) != _identity(opened):
                raise RuntimeError('Native fixture configuration restoration failed.')
    finally:
        os.close(fd)
