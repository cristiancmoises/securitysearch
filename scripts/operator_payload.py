#!/usr/bin/env python3
"""Bounded, lossless schema-2 packaging for standalone SecuritySearch operators.

This codec handles bytes only. It never executes, extracts, fetches or applies them.
Checksums detect corruption; authenticity still depends on the trusted launcher.
"""
import base64
import binascii
import gzip
import hashlib
import json
import re
import zlib

MAX_JSON = 16 * 1024 * 1024
MAX_PACKED = 8 * 1024 * 1024
MAX_RESOURCE = 8 * 1024 * 1024
MAX_TOTAL = 16 * 1024 * 1024
MAX_RESOURCES = 32
MAX_CHUNKS = 8192
MAX_PARTS = 32768
CHUNK_SIZE = 64 * 1024
_DIGEST = re.compile(r"[0-9a-f]{64}\Z")
_NAME = re.compile(r"[A-Za-z0-9][A-Za-z0-9._-]{0,127}\Z")


class PayloadError(ValueError):
    """Malformed, corrupted or excessive resource envelope."""


def _require(condition, message):
    if not condition:
        raise PayloadError(message)


def _names(names):
    _require(type(names) is list and 1 <= len(names) <= MAX_RESOURCES, "resource names")
    _require(all(type(n) is str and _NAME.fullmatch(n) for n in names), "resource name")
    _require(names == sorted(set(names)), "unordered or duplicate names")


def _pairs(items):
    result = {}
    for key, value in items:
        _require(key not in result, "duplicate JSON key")
        result[key] = value
    return result


def _constant(unused):
    raise PayloadError("non-finite JSON value")


def _unpack(payload):
    _require(type(payload) is str and 0 < len(payload) <= 4 * ((MAX_PACKED + 2) // 3),
             "encoded payload size")
    packed = base64.b64decode(payload, validate=True)
    _require(0 < len(packed) <= MAX_PACKED, "compressed payload size")
    _require(base64.b64encode(packed).decode("ascii") == payload, "noncanonical payload")
    decoder = zlib.decompressobj(16 + zlib.MAX_WBITS)
    raw = decoder.decompress(packed, MAX_JSON + 1)
    # No flush(): its length parameter is not a hard output-size bound.
    _require(len(raw) <= MAX_JSON and decoder.eof and not decoder.unused_data
             and not decoder.unconsumed_tail, "incomplete, trailing or oversized gzip")
    return json.loads(raw.decode("utf-8"), object_pairs_hook=_pairs, parse_constant=_constant)


def decode_resources(payload, expected_names):
    """Validate the whole envelope before returning any materializable resource.

    Hash checks and bounds use explicit exceptions, including under python -O.
    Reject multi-member/trailing gzip, duplicate JSON keys, aliases and unused chunks.
    """
    try:
        _names(expected_names)
        value = _unpack(payload)
        _require(type(value) is dict and set(value) == {"schema", "files", "chunks"}, "schema fields")
        _require(type(value["schema"]) is int and value["schema"] == 2, "schema version")
        files, chunks = value["files"], value["chunks"]
        _require(type(files) is dict and sorted(files) == expected_names, "unexpected resources")
        _require(type(chunks) is dict and len(chunks) <= MAX_CHUNKS, "chunk table")
        decoded_chunks = {}
        unique_bytes = 0
        for key, data in chunks.items():
            _require(type(key) is str and _DIGEST.fullmatch(key), "chunk digest")
            _require(type(data) is str and 0 < len(data) <= 4 * ((CHUNK_SIZE + 2) // 3), "chunk encoding")
            raw = base64.b64decode(data, validate=True)
            _require(0 < len(raw) <= CHUNK_SIZE, "chunk size")
            _require(base64.b64encode(raw).decode("ascii") == data, "noncanonical chunk")
            _require(hashlib.sha256(raw).hexdigest() == key, "chunk integrity")
            unique_bytes += len(raw)
            _require(unique_bytes <= MAX_TOTAL, "unique bytes")
            decoded_chunks[key] = raw
        total = parts_total = 0
        used = set()
        # Validate all lengths and references before joining even the first file.
        for row in files.values():
            _require(type(row) is dict and set(row) == {"size", "sha256", "parts"}, "resource fields")
            size, digest, parts = row["size"], row["sha256"], row["parts"]
            _require(type(size) is int and 0 <= size <= MAX_RESOURCE, "resource size")
            _require(type(digest) is str and _DIGEST.fullmatch(digest), "resource digest")
            _require(type(parts) is list and len(parts) <= MAX_PARTS, "resource parts")
            total += size
            parts_total += len(parts)
            _require(total <= MAX_TOTAL and parts_total <= MAX_PARTS, "expanded envelope limit")
            actual_size = 0
            for key in parts:
                _require(type(key) is str and key in decoded_chunks, "missing chunk")
                actual_size += len(decoded_chunks[key])
                _require(actual_size <= size, "expanded resource size")
                used.add(key)
            _require(actual_size == size, "resource length")
        _require(used == set(decoded_chunks), "unused chunks")
        result = {}
        for name, row in files.items():
            raw = b"".join(decoded_chunks[key] for key in row["parts"])
            _require(hashlib.sha256(raw).hexdigest() == row["sha256"], "resource integrity")
            result[name] = raw
        return result
    except PayloadError:
        raise
    except (ValueError, TypeError, UnicodeError, binascii.Error, zlib.error, RecursionError) as error:
        raise PayloadError("invalid resource encoding") from None


def _split_resource(name, raw):
    # File-diff boundaries are stable across cumulative Git patches. They are only
    # deduplication hints, never trusted as paths or parsed as executable commands.
    sections = re.split(br"(?m)(?=^diff --git )", raw) if name.endswith(".patch") else [raw]
    for section in sections:
        for offset in range(0, len(section), CHUNK_SIZE):
            yield section[offset:offset + CHUNK_SIZE]


def encode_resources(resources):
    """Produce a deterministic envelope, with an exact round-trip verification."""
    _require(type(resources) is dict, "resources must be a dictionary")
    names = sorted(resources)
    _names(names)
    chunks, files = {}, {}
    total = 0
    for name in names:
        raw = resources[name]
        _require(type(raw) is bytes and len(raw) <= MAX_RESOURCE, "resource input")
        total += len(raw)
        _require(total <= MAX_TOTAL, "expanded envelope limit")
        parts = []
        for part in _split_resource(name, raw):
            digest = hashlib.sha256(part).hexdigest()
            chunks.setdefault(digest, base64.b64encode(part).decode("ascii"))
            parts.append(digest)
        files[name] = {"size": len(raw), "sha256": hashlib.sha256(raw).hexdigest(), "parts": parts}
    value = {"schema": 2, "files": files, "chunks": chunks}
    raw = json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=True).encode("ascii")
    _require(len(raw) <= MAX_JSON, "resource JSON size")
    packed = gzip.compress(raw, compresslevel=9, mtime=0)
    _require(len(packed) <= MAX_PACKED, "compressed payload size")
    payload = base64.b64encode(packed).decode("ascii")
    _require(decode_resources(payload, names) == resources, "resource round trip")
    return payload
