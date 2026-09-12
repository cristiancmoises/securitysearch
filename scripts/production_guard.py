#!/usr/bin/env python3
"""Reject stale production snapshots before cutover; never log private config.

This is an optimistic guard, not a lock on other administrators or Docker clients.
Callers must still hold the deployment lock. State is observed again immediately
before the first production mutation; independent changes after that instant remain
possible and are handled by the existing identity-bound transaction/rollback.
"""
import hashlib
import json
import re

ID = re.compile(r'^[0-9a-f]{64}$')
IMAGE = re.compile(r'^sha256:[0-9a-f]{64}$')
SECTIONS = ('identity', 'configuration', 'host_configuration', 'mounts', 'networks')

class ProductionChanged(RuntimeError):
    pass

def snapshot(obj):
    """Keep only section digests in memory; secrets never enter error messages."""
    if not isinstance(obj, dict):
        raise ProductionChanged('Production inspect response is invalid.')
    state = obj.get('State') or {}
    if (state.get('Running') is not True or state.get('Status') != 'running'
            or state.get('Paused') or state.get('Restarting')
            or (state.get('Health') or {}).get('Status') != 'healthy'):
        raise ProductionChanged('Production is not running and healthy; update stopped.')
    if (not isinstance(obj.get('Id'), str) or not ID.fullmatch(obj['Id'])
            or not isinstance(obj.get('Image'), str) or not IMAGE.fullmatch(obj['Image'])
            or not isinstance(obj.get('Name'), str) or not obj['Name'].startswith('/')
            or not isinstance(state.get('StartedAt'), str) or not state['StartedAt']
            or type(obj.get('RestartCount')) is not int or obj['RestartCount'] < 0
            or not isinstance(obj.get('Config'), dict)
            or not isinstance(obj.get('HostConfig'), dict)
            or not isinstance(obj.get('Mounts'), list)
            or not isinstance((obj.get('NetworkSettings') or {}).get('Networks'), dict)):
        raise ProductionChanged('Production identity/configuration is incomplete; update stopped.')
    sections = {
        'identity': [obj['Id'], obj['Image'], obj['Name'], obj.get('Created'),
                     state['StartedAt'], obj['RestartCount']],
        'configuration': obj['Config'],
        'host_configuration': obj['HostConfig'],
        'mounts': obj['Mounts'],
        'networks': obj['NetworkSettings']['Networks'],
    }
    try:
        return {name: hashlib.sha256(json.dumps(value, sort_keys=True,
                    separators=(',', ':'), allow_nan=False).encode()).hexdigest()
                for name, value in sections.items()}
    except (TypeError, ValueError, UnicodeError):
        raise ProductionChanged('Production metadata could not be compared safely.') from None

def verify(expected, current):
    if not isinstance(expected, dict) or set(expected) != set(SECTIONS):
        raise ProductionChanged('Missing production guard snapshot; update stopped.')
    observed = snapshot(current)
    changed = [name for name in SECTIONS if expected[name] != observed[name]]
    if changed:
        raise ProductionChanged('Production changed during deployment ('+', '.join(changed)+
                                '); cutover refused. No production mutation was requested.')
