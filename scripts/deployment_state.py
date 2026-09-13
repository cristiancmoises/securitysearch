#!/usr/bin/env python3
"""Durable, bounded deployment records in an already-created backup directory."""
import json
import os
from pathlib import Path
import stat

NAMES = ('release.json', 'predeploy-cleanup.json', 'offline-audit-result.json', 'audit-only.json', 'skunkyart-live.json', 'postdeploy-cleanup.json')

class StateError(RuntimeError):
    pass

def identity(directory):
    try:
        info = Path(directory).lstat()
    except OSError as e:
        raise StateError('Deployment state directory unavailable; errno='+str(e.errno)) from e
    if not stat.S_ISDIR(info.st_mode):
        raise StateError('Deployment state directory is not a real directory.')
    return info.st_dev, info.st_ino

def write_record(directory, name, value, expected):
    """No mkdir fallback: a lost/replaced rollback directory is never repaired away."""
    if name not in NAMES:
        raise StateError('Unapproved deployment record name.')
    raw = (json.dumps(value, indent=2, allow_nan=False)+'\n').encode()
    if len(raw) > 2*1024*1024:
        raise StateError('Deployment record exceeds its size limit.')
    fd = None
    temp = None
    try:
        if identity(directory) != expected:
            raise StateError('Deployment state directory was replaced.')
        fd = os.open(directory, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
        s = os.fstat(fd)
        if (s.st_dev, s.st_ino) != expected:
            raise StateError('Deployment state identity changed before write.')
        try:
            target = os.stat(name, dir_fd=fd, follow_symlinks=False)
        except FileNotFoundError:
            target = None
        if target and not stat.S_ISREG(target.st_mode):
            raise StateError('Deployment record is not a regular file.')
        temp = '.record-'+os.urandom(16).hex()+'.tmp'
        stream_fd = os.open(temp, os.O_CREAT | os.O_EXCL | os.O_WRONLY | os.O_NOFOLLOW, 0o600, dir_fd=fd)
        with os.fdopen(stream_fd, 'wb') as stream:
            stream.write(raw)
            stream.flush()
            os.fsync(stream.fileno())
        if identity(directory) != expected:
            raise StateError('Deployment state directory changed during write.')
        os.replace(temp, name, src_dir_fd=fd, dst_dir_fd=fd)
        temp = None
        os.fsync(fd)
        if identity(directory) != expected:
            raise StateError('Deployment state directory changed after write.')
    except OSError as e:
        raise StateError('Cannot persist deployment record '+name+'; errno='+str(e.errno)) from e
    finally:
        if fd is not None:
            if temp is not None:
                try:
                    os.unlink(temp, dir_fd=fd)
                except OSError:
                    pass
            os.close(fd)
