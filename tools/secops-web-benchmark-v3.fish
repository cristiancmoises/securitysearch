#!/usr/bin/env fish
# SecOps Web Benchmark 3.0.0 — GNU Guix / Fish entry point.
# Run with: fish --no-config "$HOME/Downloads/secops-web-benchmark-v3.fish"
# Uses a temporary Guix dependency environment; does not reconfigure the system.
# Python below is plain, inspectable source; no downloads are executed as code.
# --system-deps, as the first argument, is only for an existing Python/curl setup.

set -l system_deps 0
if test (count $argv) -gt 0
    if test "$argv[1]" = --system-deps
        set system_deps 1
        set -e argv[1]
    end
end

set -l program '#!/usr/bin/env python3
"""SecOps Web Benchmark 3.0: sequential, auditable HTTP homepage measurements.

Python standard library only; curl >= 7.70 is the HTTP transport.
This is not a browser benchmark or a measurement of search-result relevance.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import html
import io
import json
import math
import os
import platform
import random
import re
import shutil
import statistics
import subprocess
import sys
import tempfile
import time
from collections import Counter
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit

VERSION = "3.0.0"
SITES = (
    ("securityops.co", "https://securityops.co/"),
    ("google.com", "https://google.com/"),
    ("yandex.com", "https://yandex.com/"),
    ("bing.com", "https://bing.com/"),
    ("search.brave.com", "https://search.brave.com/"),
    ("4get.ca", "https://4get.ca/"),
    ("duckduckgo.com", "https://duckduckgo.com/"),
)
DEFAULT_UA = "SecOpsWebBenchmark/3.0 (curl; HTTP measurement; no JavaScript)"
PROXY_KEYS = ("http_proxy", "https_proxy", "all_proxy", "HTTP_PROXY", "HTTPS_PROXY", "ALL_PROXY")
KEEP_HEADERS = ("content-type", "content-encoding", "cache-control", "age", "vary", "server",
                "x-cache", "cf-cache-status", "cf-mitigated", "retry-after")
FIELDS = (
    "phase", "round", "position", "site", "requested_url", "started_utc", "status", "reason",
    "curl_exit", "http_code", "http_version", "remote_ip", "effective_url", "redirects",
    "dns_ms", "tcp_ms", "tls_ms", "ttfb_ms", "total_ms", "redirect_ms", "body_phase_ms",
    "download_bytes", "decoded_bytes", "speed_bytes_s", "content_type", "content_encoding",
    "cache_control", "cache_status", "age", "title", "body_sha256", "phase_note", "error",
)


def utcnow() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="seconds")


def number(value):
    try:
        n = float(value)
        return n if math.isfinite(n) and n >= 0 else None
    except (TypeError, ValueError):
        return None


def millis(value):
    n = number(value)
    return round(n * 1000, 3) if n is not None else None


def difference(a, b):
    a, b = number(a), number(b)
    return round((a - b) * 1000, 3) if a is not None and b is not None and a >= b else None


def percentile(values, p=95):
    if not values:
        return None
    xs = sorted(values)
    k = (len(xs) - 1) * p / 100
    lo, hi = math.floor(k), math.ceil(k)
    return xs[lo] + (xs[hi] - xs[lo]) * (k - lo)


def clean(value, limit=500):
    # Do not allow server-supplied control sequences into terminal reports.
    return " ".join(re.sub(r"[\\x00-\\x1f\\x7f-\\x9f]", " ", str(value)).split())[:limit]


def safe_csv(value):
    # Prevent active spreadsheet formulas when opening remote-derived CSV fields.
    if isinstance(value, str) and value.lstrip().startswith(("=", "+", "-", "@")):
        return "\'" + value
    return value


def write_csv(path, rows, fields):
    with path.open("w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        for row in rows:
            writer.writerow({k: safe_csv(row.get(k)) for k in fields})


class PageText(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.title_parts = []
        self.visible_parts = []
        self.hidden = 0
        self.in_title = False
        self.html_seen = False

    def handle_starttag(self, tag, attrs):
        if tag in ("html", "head", "body"):
            self.html_seen = True
        if tag in ("script", "style"):
            self.hidden += 1
        if tag == "title":
            self.in_title = True

    def handle_endtag(self, tag):
        if tag in ("script", "style"):
            self.hidden = max(0, self.hidden - 1)
        if tag == "title":
            self.in_title = False

    def handle_data(self, text):
        if self.in_title:
            self.title_parts.append(text)
        if not self.hidden:
            self.visible_parts.append(text)


def parse_headers(text):
    """Select final-response headers, ignoring proxy CONNECT and redirect blocks."""
    current, chain = {}, []
    for line in text.splitlines():
        match = re.match(r"^HTTP/\\S+\\s+(\\d{3})", line)
        if match:
            current = {}
            chain.append(int(match.group(1)))
        elif ":" in line:
            key, value = line.split(":", 1)
            key = key.strip().lower()
            if key in KEEP_HEADERS:
                current[key] = clean(value.strip(), 1000)
    return current, chain


def classify(meta, exit_code, headers, body, parse_error=""):
    if parse_error:
        return "metadata_error", parse_error, ""
    if exit_code != 0:
        return "transport_error", "curl did not complete successfully", ""
    code = int(meta.get("http_code", meta.get("response_code", 0)) or 0)
    if not 200 <= code < 300:
        return "http_error", f"HTTP {code}", ""
    if not body:
        return "empty_response", "empty response body", ""
    content_type = str(meta.get("content_type") or headers.get("content-type", "")).lower()
    if not any(t in content_type for t in ("text/html", "application/xhtml+xml")):
        return "non_html", "response is not declared HTML", ""
    page = PageText()
    page.feed(body.decode("utf-8", "replace"))
    title = clean(" ".join(page.title_parts), 200)
    visible = clean(" ".join(page.visible_parts), 200000).lower()
    url = str(meta.get("url_effective", "")).lower()
    host = urlsplit(url).hostname or ""
    if "consent." in host or "before you continue to google" in visible or "before you continue to bing" in visible:
        return "consent_detected", "consent page detected; not a homepage result", title
    gate = (
        headers.get("cf-mitigated", "").lower() == "challenge"
        or any(x in url for x in ("/showcaptcha", "/sorry/", "/captcha", "/challenge/"))
        or any(x in title.lower() for x in ("access denied", "just a moment", "robot check", "captcha"))
        or any(x in visible for x in (
            "our systems have detected unusual traffic", "verify you are human",
            "verify that you are human", "checking your browser before accessing",
            "enable javascript and cookies to continue", "confirm you are not a robot",
        ))
    )
    if gate:
        return "challenge_detected", "probable challenge/block page; heuristic", title
    if len(visible) < 800 and any(x in visible for x in (
            "please enable javascript", "javascript is required", "turn on javascript")):
        return "javascript_required", "short JavaScript-required interstitial", title
    if not page.html_seen:
        return "unrecognized_html", "no HTML document structure in inspected prefix", title
    for key in ("time_starttransfer", "time_total"):
        if number(meta.get(key)) is None:
            return "metadata_error", f"missing or invalid {key}", title
    if float(meta["time_total"]) <= 0 or float(meta["time_starttransfer"]) > float(meta["time_total"]):
        return "metadata_error", "inconsistent or nonpositive total timing", title
    return "eligible", "2xx HTML; no detected gate (not proof of equivalent content)", title


def extract_metrics(meta, proxies):
    redirects = int(meta.get("num_redirects") or 0)
    simple = redirects == 0 and not proxies and str(meta.get("http_version", "")) != "3"
    output = {
        "http_code": meta.get("http_code", meta.get("response_code", 0)),
        "http_version": str(meta.get("http_version", "")),
        "remote_ip": clean(meta.get("remote_ip", "")),
        "effective_url": clean(meta.get("url_effective", ""), 3000),
        "redirects": redirects,
        "dns_ms": millis(meta.get("time_namelookup")) if simple else None,
        "tcp_ms": difference(meta.get("time_connect"), meta.get("time_namelookup")) if simple else None,
        "tls_ms": difference(meta.get("time_appconnect"), meta.get("time_connect")) if simple else None,
        "ttfb_ms": millis(meta.get("time_starttransfer")),
        "total_ms": millis(meta.get("time_total")),
        "redirect_ms": millis(meta.get("time_redirect")),
        "body_phase_ms": difference(meta.get("time_total"), meta.get("time_starttransfer")) if simple else None,
        "download_bytes": number(meta.get("size_download")),
        "speed_bytes_s": number(meta.get("speed_download")),
        "phase_note": "single HTTPS request; direct connection phases" if simple else
                      "phase decomposition omitted: redirects, proxy configuration, or HTTP/3",
    }
    return output


def curl_environment():
    env = os.environ.copy()
    # Honor an explicit trust configuration, including custom enterprise roots.
    if not any(env.get(k) for k in ("CURL_CA_BUNDLE", "SSL_CERT_FILE", "SSL_CERT_DIR")):
        candidates = []
        if env.get("GUIX_ENVIRONMENT"):
            candidates.append(Path(env["GUIX_ENVIRONMENT"]) / "etc/ssl/certs/ca-certificates.crt")
        candidates += [Path("/etc/ssl/certs/ca-certificates.crt"),
                       Path.home() / ".guix-profile/etc/ssl/certs/ca-certificates.crt"]
        for candidate in candidates:
            if candidate.is_file():
                env["CURL_CA_BUNDLE"] = str(candidate)
                break
    return env


def make_command(curl, url, body_path, headers_path, args):
    cmd = [curl, "-q", "--silent", "--show-error", "--location", "--max-redirs", "5",
           "--compressed", "--proto", "=https", "--proto-redir", "=https", "--retry", "0",
           "--connect-timeout", str(min(10, args.timeout)), "--max-time", str(args.timeout),
           "--max-filesize", str(16 * 1024 * 1024),
           "--user-agent", args.user_agent,
           "--header", "Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8",
           "--header", "Accept-Language: en-US,en;q=0.8",
           "--output", str(body_path), "--dump-header", str(headers_path),
           "--write-out", "%{json}"]
    if args.ip != "auto":
        cmd.append("--ipv" + args.ip)
    cmd += ["--url", url]
    return cmd


def measure(curl, site, url, args, env, proxies, work):
    row = {"site": site, "requested_url": url, "started_utc": utcnow()}
    body_path, headers_path = work / "body.tmp", work / "headers.tmp"
    body_path.unlink(missing_ok=True)
    headers_path.unlink(missing_ok=True)
    parse_error, meta = "", {}
    try:
        process = subprocess.run(make_command(curl, url, body_path, headers_path, args),
                                 stdout=subprocess.PIPE, stderr=subprocess.PIPE, env=env,
                                 timeout=args.timeout + 5, check=False)
        exit_code = process.returncode
        error = clean(process.stderr.decode("utf-8", "replace"), 1800)
        try:
            meta = json.loads(process.stdout)
            if not isinstance(meta, dict):
                raise ValueError("curl JSON is not an object")
        except (ValueError, TypeError) as exc:
            parse_error = f"unusable curl JSON: {exc}"
    except subprocess.TimeoutExpired:
        exit_code, error = 124, "outer process timeout"
    headers, chain = parse_headers(headers_path.read_text(encoding="latin-1") if headers_path.exists() else "")
    size, digest, prefix = 0, hashlib.sha256(), b""
    if body_path.exists():
        with body_path.open("rb") as f:
            while chunk := f.read(65536):
                size += len(chunk)
                digest.update(chunk)
                if len(prefix) < 2 * 1024 * 1024:
                    prefix += chunk[:2 * 1024 * 1024 - len(prefix)]
    status, reason, title = classify(meta, exit_code, headers, prefix, parse_error)
    row.update(extract_metrics(meta, proxies))
    row.update(status=status, reason=reason, curl_exit=exit_code, error=error, title=title,
               decoded_bytes=size, body_sha256=digest.hexdigest() if size else "",
               content_type=clean(meta.get("content_type") or headers.get("content-type", "")),
               content_encoding=headers.get("content-encoding", ""),
               cache_control=headers.get("cache-control", ""),
               cache_status=headers.get("cf-cache-status", headers.get("x-cache", "")),
               age=headers.get("age", ""))
    diagnostics = {"row": row, "response_status_chain": chain, "headers": headers,
                   "curl_timings_seconds": {k: v for k, v in meta.items() if k.startswith("time_")}}
    return row, diagnostics


def round_order(round_number, rng, base):
    if (round_number - 1) % len(SITES) == 0:
        rng.shuffle(base)
    offset = (round_number - 1) % len(SITES)
    return base[offset:] + base[:offset]


def summarize(rows):
    output = []
    for site, _ in SITES:
        rs = [r for r in rows if r["phase"] == "measured" and r["site"] == site]
        ok = [r for r in rs if r["status"] == "eligible"]
        s = {"site": site, "scheduled": len(rs), "eligible": len(ok),
             "attempted": sum(r["status"] != "skipped" for r in rs),
             "excluded": sum(r["status"] not in ("eligible", "skipped") for r in rs),
             "skipped": sum(r["status"] == "skipped" for r in rs),
             "status_counts": dict(Counter(r["status"] for r in rs))}
        for key in ("dns_ms", "tcp_ms", "tls_ms", "ttfb_ms", "total_ms", "download_bytes"):
            values = [number(r.get(key)) for r in ok if number(r.get(key)) is not None]
            s[key + "_median"] = statistics.median(values) if values else None
            if key in ("ttfb_ms", "total_ms"):
                s[key + "_p95"] = percentile(values)
        output.append(s)
    return sorted(output, key=lambda s: (s["total_ms_median"] is None, s["total_ms_median"] or 0))


def comparisons(rows):
    by_site = {}
    for site, _ in SITES:
        by_site[site] = {r["round"]: r["total_ms"] for r in rows
                         if r["phase"] == "measured" and r["site"] == site and r["status"] == "eligible"}
    sec = by_site["securityops.co"]
    result = []
    for site, _ in SITES[1:]:
        common = sorted(sec.keys() & by_site[site].keys())
        if not common:
            result.append({"competitor": site, "paired_rounds": 0})
            continue
        a = statistics.median([sec[k] for k in common])
        b = statistics.median([by_site[site][k] for k in common])
        result.append({"competitor": site, "paired_rounds": len(common),
                       "securityops_median_ms": a, "competitor_median_ms": b,
                       "difference_ms": a - b, "difference_percent": (a - b) / b * 100 if b else None})
    return result


def fmt(value, places=1):
    return f"{value:.{places}f}" if value is not None else "-"


def table(headers, body, right=()):
    """An ASCII grid: no ANSI escapes, Unicode width problems, or extra packages."""
    records = [[clean(value) for value in row] for row in body]
    widths = [max([len(str(h))] + [len(row[i]) for row in records]) for i, h in enumerate(headers)]
    edge = "+-" + "-+-".join("-" * w for w in widths) + "-+"
    def line(row):
        return "| " + " | ".join(str(v).rjust(widths[i]) if i in right else str(v).ljust(widths[i])
                                   for i, v in enumerate(row)) + " |"
    return "\\n".join([edge, line(headers), edge] + [line(row) for row in records] + [edge])


def leaderboard(rows, metadata):
    """Rank matched rounds only; never give a failed or sparsely sampled site a trophy."""
    sums = {s["site"]: s for s in summarize(rows)}
    samples = {site: {} for site, _ in SITES}
    for row in rows:
        value = number(row.get("total_ms"))
        if (row.get("phase") == "measured" and row.get("status") == "eligible"
                and row.get("site") in samples and value is not None and value > 0):
            samples[row["site"]][row["round"]] = row
    expected = int(metadata.get("runs", max((r.get("round", 0) for r in rows
                                            if r.get("phase") == "measured"), default=0)))
    required = max(5, math.ceil(expected * 0.8))
    paused = metadata.get("paused_sites", {})
    candidates = [site for site, _ in SITES if len(samples[site]) >= required and site not in paused]
    common = sorted(set.intersection(*(set(samples[s]) for s in candidates))) if candidates else []
    comparable = len(candidates) >= 2 and len(common) >= required
    completed = metadata.get("state") == "completed"
    items = []
    for site, _ in SITES:
        own = samples[site]
        matched = comparable and site in candidates
        subset = [own[n] for n in common] if matched else list(own.values())
        totals = [number(r.get("total_ms")) for r in subset]
        ttfbs = [number(r.get("ttfb_ms")) for r in subset if number(r.get("ttfb_ms")) is not None]
        total = statistics.median(totals) if totals else None
        if site in paused:
            status = "BLOCKED/STOPPED"
        elif not own:
            status = "NO VALID DATA"
        elif len(own) < required:
            status = "LOW SAMPLE"
        elif not comparable:
            status = "NOT COMPARABLE"
        elif not completed:
            status = "PROVISIONAL"
        else:
            status = "RANKED"
        items.append({"site": site, "rank": None, "status": status,
                      "valid": len(own), "planned": expected, "samples_used": len(subset),
                      "matched": matched, "median_ms": total,
                      "ttfb_median_ms": statistics.median(ttfbs) if ttfbs else None,
                      "p95_ms": percentile(totals),
                      "gap_ms": None, "slower_percent": None,
                      "excluded": sums[site]["excluded"], "skipped": sums[site]["skipped"]})
    ranked = sorted([i for i in items if i["matched"]], key=lambda i: (i["median_ms"], i["site"]))
    if ranked:
        first = ranked[0]["median_ms"]
        previous_key, last_rank = None, 0
        for position, item in enumerate(ranked, 1):
            # Compare at the same 0.001 ms precision displayed in the table.
            key = round(item["median_ms"], 3)
            if key != previous_key:
                last_rank = position
            item["rank"] = last_rank
            item["gap_ms"] = item["median_ms"] - first
            item["slower_percent"] = (item["median_ms"] / first - 1) * 100
            previous_key = key
    winners = [i["site"] for i in ranked if i["rank"] == 1] if completed else []
    for item in ranked:
        if item["site"] in winners:
            item["status"] = "WINNER" if len(winners) == 1 else "CO-WINNER"
    unranked = sorted([i for i in items if not i["matched"]],
                      key=lambda i: (i["median_ms"] is None, i["median_ms"] or 0, i["site"]))
    all_sites = len(ranked) == len(SITES)
    scope = f"all {len(SITES)} sites" if all_sites else f"{len(ranked)} of {len(SITES)} qualified sites (not all sites)"
    if not comparable:
        headline = "NO WINNER - insufficient comparable valid samples"
    elif not completed:
        headline = "NO FINAL WINNER - interrupted or incomplete run"
    else:
        label = "WINNER" if len(winners) == 1 else "TIED WINNERS"
        headline = label + ": " + ", ".join(winners) + " | " + scope
    if comparable:
        detail = (f"Lowest median complete HTML delivery: {ranked[0][\'median_ms\']:.3f} ms; "
                  f"{len(common)} matching eligible rounds per ranked site.")
        if winners and len(winners) == 1 and len(ranked) > 1:
            other = ranked[1]
            gap = other["median_ms"] - ranked[0]["median_ms"]
            reduction = gap / other["median_ms"] * 100
            detail += f" {reduction:.1f}% lower time than runner-up {other[\'site\']} ({gap:.3f} ms less)."
    else:
        detail = (f"Require at least two unpaused sites with {required} matching valid rounds "
                  f"(minimum 5 and 80% of {expected} planned rounds, rounded up).")
    return {"criterion": "lowest median complete HTML delivery time on matching eligible rounds",
            "required_samples": required, "common_rounds": common, "ranked_count": len(ranked),
            "all_sites_compared": all_sites and comparable,
            "completed": completed, "winners": winners, "headline": headline, "detail": detail,
            "scope": scope, "tie_precision_ms": 0.001, "items": ranked + unranked}


LEADER_HEADERS = ("Rank", "Site", "OK/Plan", "TTFB ms", "HTML ms", "p95 ms", "Slower %", "Result")


def leaderboard_cells(board):
    output = []
    for item in board["items"]:
        output.append((str(item["rank"]) if item["rank"] is not None else "-", item["site"],
                       f"{item[\'valid\']}/{item[\'planned\']}", fmt(item["ttfb_median_ms"], 3),
                       fmt(item["median_ms"], 3), fmt(item["p95_ms"], 3),
                       "+" + fmt(item["slower_percent"], 1) if item["slower_percent"] is not None else "-",
                       item["status"]))
    return output


def html_report(board, text, metadata):
    esc = lambda value: html.escape(str(value), quote=True)
    trs = []
    for item, cells in zip(board["items"], leaderboard_cells(board)):
        cls = "winner" if item["site"] in board["winners"] else "unranked" if item["rank"] is None else "ranked"
        cells_html = "".join((f\'<th scope="row">{esc(v)}</th>\' if i == 1 else f\'<td>{esc(v)}</td>\')
                             for i, v in enumerate(cells))
        trs.append(f\'<tr class="{cls}">\' + cells_html + \'</tr>\')
    headers = "".join(f\'<th scope="col">{esc(h)}</th>\' for h in LEADER_HEADERS)
    foot = ("Ranked rows use the SAME matching rounds. Unranked rows show descriptive own-sample timings only. "
            "Slower % = (site median / leader median - 1) x 100; lower is better. "
            "Minimum coverage: " + str(board["required_samples"]) + " eligible matching rounds. "
            "Ties are reported at 0.001 ms display precision; no statistical significance is claimed.")
    css = """
    :root{color-scheme:dark light;font-family:system-ui,-apple-system,sans-serif;background:#0c121b;color:#e5edf5}
    *{box-sizing:border-box}body{margin:0;padding:32px 22px 56px}main{max-width:1240px;margin:auto}
    .eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:12px;color:#81cbd6;font-weight:700}
    h1{font-size:clamp(25px,4vw,42px);margin:10px 0}h2{font-size:21px;margin:12px 0;overflow-wrap:anywhere}
    p{line-height:1.65;margin:8px 0}.muted{color:#acbccc}header{margin-bottom:25px}
    .hero{background:#12222e;border:1px solid #326b75;border-left:5px solid #63d7e5;border-radius:12px;padding:22px}
    .cards{display:flex;flex-wrap:wrap;gap:12px;margin:18px 0 24px}.card{flex:1;min-width:160px;background:#141f2d;border:1px solid #293848;border-radius:10px;padding:17px}
    .card span{display:block;font-size:12px;color:#acbccc;text-transform:uppercase;letter-spacing:.06em}.card strong{display:block;font-size:24px;margin-top:7px}
    .tablewrap{border:1px solid #293848;border-radius:12px;overflow:auto;background:#111b27}
    table{border-collapse:collapse;width:100%;font-variant-numeric:tabular-nums;white-space:nowrap}
    caption{text-align:left;padding:17px;font-weight:700}thead th{background:#1c2a3b;color:#bfcedc;text-transform:uppercase;font-size:11px;letter-spacing:.04em}
    td,th{padding:15px 13px;border-bottom:1px solid #263444;text-align:right}th:nth-child(2),td:nth-child(2),th:last-child,td:last-child{text-align:left}
    tbody th{font-weight:600}tbody tr:last-child td,tbody tr:last-child th{border:0}
    .winner{background:#153537}.winner td:last-child{color:#92f4db;font-weight:800}.unranked{color:#a2b1c0}
    .note{margin-top:16px;font-size:13px;color:#acbccc}details{margin-top:28px;border-top:1px solid #293848;padding-top:20px}
    summary{cursor:pointer;font-weight:650}pre{font:12px/1.6 ui-monospace,monospace;overflow:auto;white-space:pre;margin-top:16px}
    footer{font-size:13px;color:#acbccc;margin-top:24px}a{color:#84e0e8}
    @media print{:root{background:white;color:black}.hero,.card,.tablewrap{background:white;color:black;border-color:#777}.muted,.note,footer{color:#333}thead th{background:#eee;color:black}.winner{background:#e6f3f1}.winner td:last-child{color:#075245}.unranked{color:#444}body{padding:0}a{color:black}}
    """
    return ("<!doctype html><html lang=\\"en\\"><head><meta charset=\\"utf-8\\">"
            "<meta http-equiv=\\"Content-Security-Policy\\" content=\\"default-src \'none\'; style-src \'unsafe-inline\'; base-uri \'none\'; form-action \'none\'\\">"
            "<meta name=\\"viewport\\" content=\\"width=device-width,initial-scale=1\\">"
            "<title>SecOps Web Benchmark - Final ranking</title><style>" + css + "</style></head><body><main>"
            \'<header><div class="eyebrow">SecOps Web Benchmark \' + esc(VERSION) + \'</div>\'
            \'<h1>Homepage delivery leaderboard</h1><p class="muted">Seven targets. Measured from this machine and network, at this time.</p></header>\'
            \'<section class="hero"><div class="eyebrow">Final verdict</div><h2>\' + esc(board["headline"]) + \'</h2><p>\' + esc(board["detail"]) + \'</p></section>\'
            \'<div class="cards"><div class="card"><span>Ranked targets</span><strong>\' + str(board["ranked_count"]) + \' / \' + str(len(SITES)) + \'</strong></div>\'
            \'<div class="card"><span>Matching rounds</span><strong>\' + str(len(board["common_rounds"])) + \'</strong></div>\'
            \'<div class="card"><span>Winning metric</span><strong>Median HTML</strong></div></div>\'
            \'<div class="tablewrap"><table><caption>Fastest to slowest - milliseconds; lower is better</caption><thead><tr>\' + headers + \'</tr></thead><tbody>\'
            + \'\'.join(trs) + \'</tbody></table></div><p class="note">\' + esc(foot) + \'</p>\'
            \'<p class="note">Eligible means completed 2xx HTML without a detected gate. Validation is heuristic, not proof of equivalent content.</p>\'
            \'<details><summary>Sample outcomes, SecurityOps comparisons and methodology</summary><pre>\' + esc(text) + \'</pre></details>\'
            \'<footer><p>HTTP homepage delivery only. Not rendered browser speed, search-result latency, or search quality.</p><p>State: \' + esc(metadata["state"])
            + \' &middot; Finished (UTC): \' + esc(metadata.get("finished_utc", "not recorded"))
            + \'</p><p><a href="results.csv">Raw measurements</a> &middot; <a href="leaderboard.csv">Ranking CSV</a> &middot; <a href="summary.txt">Text report</a></p></footer></main></body></html>\')


def report(rows, out, metadata):
    sums, pairs = summarize(rows), comparisons(rows)
    board = leaderboard(rows, metadata)
    lines = ["SECOPS WEB BENCHMARK " + VERSION, "HTTP HOMEPAGES ONLY - NOT A BROWSER SCORE",
             "State: " + clean(metadata["state"]), "Output: " + clean(out), "",
             "SecurityOps comparison: medians over matching eligible rounds only.",
             "Negative difference means lower measured HTML transfer time for SecurityOps."]
    for pair in pairs:
        if not pair["paired_rounds"]:
            lines.append(f\'  vs {pair["competitor"]:<18}: not comparable (no matching eligible rounds)\')
        else:
            lines.append(f\'  vs {pair["competitor"]:<18}: {pair["difference_ms"]:+.1f} ms, {fmt(pair["difference_percent"])}%, paired n={pair["paired_rounds"]} (descriptive)\')
    lines += ["", "Response outcomes (measured rounds):",
              table(("Site", "Eligible", "Failed/excluded", "Skipped"),
                    [(s["site"], s["eligible"], s["excluded"], s["skipped"]) for s in sums], (1, 2, 3))]
    gates = {r["site"] + ": " + r.get("reason", "") for r in rows if r["status"] == "skipped"}
    lines += ["  " + clean(g) for g in sorted(gates)]
    lines += ["", "Method and limits:",
              "  Sequential requests; randomized, balanced rotating positions per seven-round cycle.",
              "  Warm-up excluded. Fresh curl process per sample; no cookie jar, retries or cache-busting.",
              "  OS/resolver/CDN caches are NOT flushed; this is not a fully cold-cache test.",
              "  HTTPS verification remains enabled. Redirect-chain times are included and saved.",
              "  Connection-phase decomposition is omitted with redirects, proxies or HTTP/3.",
              "  Eligible = completed 2xx HTML with no detected gate; validation is heuristic.",
              "  Heuristics can miss consent/challenge pages or a JavaScript app shell.",
              "  No CSS/JS/image loading; no browser metrics, rendered-page or search-result timing.",
              "  p95 is a descriptive sample percentile. No statistical-significance claim.",
              "  Comparisons apply to this machine and network at this time, not global performance.",
              "  HTML bodies and cookies are not retained; proxy secret values are not logged.",
              "", "Saved reports: report.html | summary.txt | leaderboard.csv | winner.json | results.csv",
              "", "FINAL LEADERBOARD - FASTEST TO SLOWEST",
              "Ranking: median complete HTML delivery, on matching eligible rounds.",
              f\'Qualification: >= {board["required_samples"]} matching valid rounds; minimum 5 and 80% of planned runs.\',
              "Unranked timings are own-sample observations only; they are NOT ranked against the leader.",
              "Slower % = (site median / leader median - 1) x 100. Times below are milliseconds.", "",
              table(LEADER_HEADERS, leaderboard_cells(board), (0, 2, 3, 4, 5, 6)), "",
              board["headline"], board["detail"],
              "Scope: HTTP homepage delivery only. Not browser speed or search quality."]
    text = "\\n".join(lines) + "\\n"
    (out / "summary.txt").write_text(text, encoding="utf-8")
    (out / "summary.json").write_text(json.dumps({"metadata": metadata, "sites": sums, "comparisons": pairs,
                                                  "leaderboard": board}, indent=2, ensure_ascii=False), encoding="utf-8")
    (out / "winner.json").write_text(json.dumps({k: v for k, v in board.items() if k != "items"}, indent=2), encoding="utf-8")
    columns = [k for k in sums[0] if k != "status_counts"]
    write_csv(out / "summary.csv", sums, columns)
    write_csv(out / "leaderboard.csv", board["items"], tuple(board["items"][0]))
    write_csv(out / "comparisons.csv", pairs, ("competitor", "paired_rounds", "securityops_median_ms", "competitor_median_ms", "difference_ms", "difference_percent"))
    (out / "report.html").write_text(html_report(board, text, metadata), encoding="utf-8")
    (out / "metadata.json").write_text(json.dumps(metadata, indent=2, ensure_ascii=False), encoding="utf-8")
    return text


def arguments(argv=None):
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--runs", type=int, default=21, help="measured rounds, 1..100 (default: 21; three balanced seven-site cycles)")
    p.add_argument("--warmup", type=int, default=1, help="excluded warm-up rounds, 0..3")
    p.add_argument("--pause", type=float, default=1.0, help="seconds between requests, 0.2..60")
    p.add_argument("--timeout", type=float, default=30.0, help="per-request deadline, 2..120 seconds")
    p.add_argument("--ip", choices=("auto", "4", "6"), default="auto")
    p.add_argument("--seed", type=int, help="reproducible order; generated and saved when omitted")
    p.add_argument("--output-root", type=Path, default=Path.home() / "Downloads/securityops-benchmarks")
    p.add_argument("--user-agent", default=DEFAULT_UA)
    p.add_argument("--self-test", action="store_true", help="run embedded offline tests; no public requests")
    args = p.parse_args(argv)
    for name, lo, hi in (("runs", 1, 100), ("warmup", 0, 3), ("pause", .2, 60), ("timeout", 2, 120)):
        value = getattr(args, name)
        if not math.isfinite(value) or not lo <= value <= hi:
            p.error(f"--{name} must be between {lo} and {hi}")
    if not args.user_agent or re.search(r"[\\r\\n\\x00]", args.user_agent):
        p.error("--user-agent must be a non-empty single line")
    return args


def self_test():
    import unittest

    class Tests(unittest.TestCase):
        def setUp(self):
            self.meta = {"http_code": 200, "http_version": "2", "num_redirects": 0,
                         "url_effective": "https://example.org/", "content_type": "text/html",
                         "time_namelookup": .01, "time_connect": .03, "time_appconnect": .07,
                         "time_starttransfer": .1, "time_total": .2, "time_redirect": 0}
            self.body = b"<html><head><title>Search</title></head><body>Search the web</body></html>"

        def test_eligible(self):
            self.assertEqual(classify(self.meta, 0, {}, self.body)[0], "eligible")

        def test_http_errors_excluded(self):
            for code in (301, 403, 404, 429, 500, 503):
                self.assertEqual(classify(dict(self.meta, http_code=code), 0, {}, self.body)[0], "http_error")

        def test_failed_transfer_excluded(self):
            self.assertEqual(classify(self.meta, 28, {}, self.body)[0], "transport_error")

        def test_empty_excluded(self):
            self.assertEqual(classify(self.meta, 0, {}, b"")[0], "empty_response")

        def test_non_html_excluded(self):
            self.assertEqual(classify(dict(self.meta, content_type="application/json"), 0, {}, self.body)[0], "non_html")

        def test_gate_header(self):
            self.assertEqual(classify(self.meta, 0, {"cf-mitigated": "challenge"}, self.body)[0], "challenge_detected")

        def test_gate_title(self):
            self.assertEqual(classify(self.meta, 0, {}, self.body.replace(b"Search</title>", b"Just a moment</title>"))[0], "challenge_detected")

        def test_gate_url(self):
            self.assertEqual(classify(dict(self.meta, url_effective="https://google.com/sorry/index"), 0, {}, self.body)[0], "challenge_detected")

        def test_consent(self):
            self.assertEqual(classify(dict(self.meta, url_effective="https://consent.google.com/"), 0, {}, self.body)[0], "consent_detected")

        def test_script_strings_ignored(self):
            body = self.body.replace(b"</body>", b\'<script>var warning="verify you are human"</script></body>\')
            self.assertEqual(classify(self.meta, 0, {}, body)[0], "eligible")

        def test_javascript_interstitial(self):
            self.assertEqual(classify(self.meta, 0, {}, b"<html>Please enable JavaScript</html>")[0], "javascript_required")

        def test_headers_final_only(self):
            hs, chain = parse_headers("HTTP/1.1 200 Connection established\\r\\n\\r\\nHTTP/2 302\\r\\nAge: 9\\r\\nSet-Cookie: secret\\r\\n\\r\\nHTTP/2 200\\r\\nContent-Type: text/html\\r\\n")
            self.assertEqual(chain, [200, 302, 200])
            self.assertEqual(hs, {"content-type": "text/html"})

        def test_direct_metrics(self):
            m = extract_metrics(self.meta, [])
            self.assertEqual((m["dns_ms"], m["tcp_ms"], m["tls_ms"]), (10, 20, 40))

        def test_redirect_phases_blank(self):
            self.assertIsNone(extract_metrics(dict(self.meta, num_redirects=1), [])["tcp_ms"])

        def test_proxy_phases_blank(self):
            self.assertIsNone(extract_metrics(self.meta, ["https_proxy"])["dns_ms"])

        def test_negative_phase_not_clamped(self):
            self.assertIsNone(difference(.01, .02))

        def test_percentile(self):
            self.assertEqual(percentile([10, 20, 30, 40]), 38.5)
            self.assertIsNone(percentile([]))

        def test_non_finite_rejected(self):
            self.assertIsNone(number("nan"))
            self.assertIsNone(number("inf"))
            self.assertIsNone(number(-1))

        def test_all_failed_sites_visible(self):
            rs = [{"phase": "measured", "round": 1, "site": site, "status": "http_error"} for site, _ in SITES]
            self.assertEqual(len(summarize(rs)), len(SITES))
            self.assertTrue(all(s["total_ms_median"] is None for s in summarize(rs)))

        def test_matched_comparisons(self):
            rs = [{"phase": "measured", "round": n, "site": site, "status": "eligible", "total_ms": v}
                  for n, site, v in ((1, "securityops.co", 100), (2, "securityops.co", 2000), (1, "google.com", 200))]
            self.assertEqual(comparisons(rs)[0]["difference_percent"], -50)
            self.assertEqual(comparisons(rs)[0]["paired_rounds"], 1)

        def test_balanced_positions(self):
            rng, base, seen = random.Random(42), list(SITES), {}
            for r in range(1, len(SITES) + 1):
                for pos, (site, _) in enumerate(round_order(r, rng, base)):
                    seen.setdefault(site, set()).add(pos)
            self.assertTrue(all(len(p) == len(SITES) for p in seen.values()))

        def test_url_and_security_flags(self):
            args = arguments([])
            cmd = make_command("curl", "https://securityops.co/", Path("a b"), Path("h"), args)
            self.assertEqual(cmd[1], "-q")
            self.assertEqual(cmd[-1], "https://securityops.co/")
            self.assertNotIn("--insecure", cmd)
            self.assertNotIn("--parallel", cmd)
            self.assertNotIn("--cookie", cmd)

        def test_csv_formula_protection(self):
            self.assertEqual(safe_csv("=cmd()"), "\'=cmd()")
            self.assertEqual(safe_csv(-20), -20)

        def test_terminal_escape_cleaning(self):
            self.assertNotIn("\\x1b", clean("hello\\x1b[31m"))

        def test_report_escapes_html(self):
            with tempfile.TemporaryDirectory() as d:
                out = Path(d)
                report([], out, {"state": "<script>alert(1)</script>"})
                self.assertNotIn("<script>", (out / "report.html").read_text())
                self.assertEqual(len(json.loads((out / "summary.json").read_text())["sites"]), len(SITES))

        def ranking_rows(self, rounds=7):
            return [{"phase": "measured", "round": n, "site": site, "status": "eligible",
                     "total_ms": 100.0 + index * 100, "ttfb_ms": 50.0 + index * 40}
                    for n in range(1, rounds + 1) for index, (site, _) in enumerate(SITES)]

        def test_seven_exact_targets(self):
            self.assertEqual(len(SITES), 7)
            self.assertIn(("4get.ca", "https://4get.ca/"), SITES)
            self.assertIn(("duckduckgo.com", "https://duckduckgo.com/"), SITES)
            self.assertEqual(len({s for s, _ in SITES}), 7)

        def test_default_is_three_balanced_cycles(self):
            self.assertEqual(arguments([]).runs, 21)
            rng, base, counts = random.Random(17), list(SITES), {}
            for n in range(1, 22):
                for pos, (site, _) in enumerate(round_order(n, rng, base)):
                    counts[(site, pos)] = counts.get((site, pos), 0) + 1
            self.assertEqual(len(counts), 49)
            self.assertEqual(set(counts.values()), {3})

        def test_winner_and_sort(self):
            board = leaderboard(self.ranking_rows(), {"state": "completed", "runs": 7})
            self.assertEqual(board["winners"], ["securityops.co"])
            self.assertTrue(board["all_sites_compared"])
            self.assertEqual([i["rank"] for i in board["items"]], list(range(1, 8)))
            self.assertEqual(board["items"][1]["slower_percent"], 100)
            self.assertIn("50.0% lower time", board["detail"])

        def test_every_site_can_win(self):
            for target, _ in SITES:
                rows = self.ranking_rows()
                for row in rows:
                    if row["site"] == target:
                        row["total_ms"], row["ttfb_ms"] = 10, 5
                self.assertEqual(leaderboard(rows, {"state": "completed", "runs": 7})["winners"], [target])

        def test_ties_are_shared_not_alphabetical_winners(self):
            rows = self.ranking_rows()
            for row in rows:
                if row["site"] == "duckduckgo.com":
                    row["total_ms"] = 100
            b = leaderboard(rows, {"state": "completed", "runs": 7})
            self.assertEqual(set(b["winners"]), {"securityops.co", "duckduckgo.com"})
            self.assertEqual([i["rank"] for i in b["items"][:3]], [1, 1, 3])
            self.assertIn("TIED WINNERS:", b["headline"])

        def test_http_error_cannot_win(self):
            rows = self.ranking_rows()
            for row in rows:
                if row["site"] == "duckduckgo.com":
                    row.update(status="http_error", total_ms=1)
            b = leaderboard(rows, {"state": "completed", "runs": 7})
            self.assertEqual(b["ranked_count"], 6)
            self.assertFalse(b["all_sites_compared"])
            self.assertIn("not all sites", b["headline"])
            self.assertIsNone(next(i for i in b["items"] if i["site"] == "duckduckgo.com")["rank"])

        def test_paused_site_cannot_win_even_with_enough_prior_samples(self):
            b = leaderboard(self.ranking_rows(), {"state": "completed", "runs": 7,
                           "paused_sites": {"securityops.co": "challenge"}})
            self.assertEqual(b["winners"], ["google.com"])
            self.assertEqual(b["ranked_count"], 6)

        def test_short_run_has_no_winner(self):
            b = leaderboard(self.ranking_rows(2), {"state": "completed", "runs": 2})
            self.assertFalse(b["winners"])
            self.assertTrue(all(i["rank"] is None for i in b["items"]))

        def test_single_site_has_no_winner(self):
            rows = [r for r in self.ranking_rows() if r["site"] == "4get.ca"]
            self.assertFalse(leaderboard(rows, {"state": "completed", "runs": 7})["winners"])

        def test_interrupted_run_has_no_final_winner(self):
            b = leaderboard(self.ranking_rows(), {"state": "interrupted; partial data", "runs": 7})
            self.assertFalse(b["winners"])
            self.assertTrue(all(i["status"] == "PROVISIONAL" for i in b["items"]))

        def test_warmup_does_not_affect_winner(self):
            rows = self.ranking_rows()
            rows += [dict(r, phase="warmup", total_ms=0.001) for r in rows if r["site"] == "bing.com"]
            self.assertEqual(leaderboard(rows, {"state": "completed", "runs": 7})["winners"], ["securityops.co"])

        def test_matched_ranking_removes_unshared_round(self):
            rows = self.ranking_rows()
            rows = [r for r in rows if not (r["round"] == 7 and r["site"] == "4get.ca")]
            for r in rows:
                if r["site"] == "google.com" and r["round"] == 7:
                    r["total_ms"] = 999999
            b = leaderboard(rows, {"state": "completed", "runs": 7})
            self.assertEqual(b["common_rounds"], list(range(1, 7)))
            self.assertTrue(all(i["samples_used"] == 6 for i in b["items"]))
            self.assertEqual(next(i for i in b["items"] if i["site"] == "google.com")["p95_ms"], 200)

        def test_insufficient_common_rounds_has_no_winner(self):
            # Six observations per site, but a different missing round for every site.
            rows = [r for r in self.ranking_rows()
                    if r["round"] != 1 + [s for s, _ in SITES].index(r["site"])]
            b = leaderboard(rows, {"state": "completed", "runs": 7})
            self.assertEqual(b["common_rounds"], [])
            self.assertFalse(b["winners"])

        def test_default_ranking_minimum(self):
            self.assertEqual(leaderboard([], {"state": "completed", "runs": 21})["required_samples"], 17)

        def test_zero_and_reversed_timing_excluded(self):
            for total in (0, 0.05):
                self.assertEqual(classify(dict(self.meta, time_total=total), 0, {}, self.body)[0], "metadata_error")

        def test_ascii_table_alignment(self):
            text = table(("Site", "ms"), (("4get.ca", "10.000"), ("duckduckgo.com", "99999.123")), (1,))
            lines = text.splitlines()
            self.assertEqual(len(set(map(len, lines))), 1)
            self.assertNotIn("\\x1b", text)

        def test_html_table_and_json_winner_consistent(self):
            with tempfile.TemporaryDirectory() as d:
                out = Path(d)
                text = report(self.ranking_rows(), out, {"state": "completed", "runs": 7})
                winner = json.loads((out / "winner.json").read_text())
                doc = (out / "report.html").read_text()
                self.assertEqual(winner["winners"], ["securityops.co"])
                self.assertIn(\'<table>\', doc)
                self.assertEqual(doc.count(\'<tr class="winner">\'), 1)
                self.assertNotIn(\'<script\', doc)
                self.assertIn("WINNER: securityops.co", text)
                self.assertTrue((out / "leaderboard.csv").exists())
                self.assertGreater(text.index("FINAL LEADERBOARD"), text.index("Method and limits"))

    result = unittest.TextTestRunner(verbosity=2).run(unittest.defaultTestLoader.loadTestsFromTestCase(Tests))
    return 0 if result.wasSuccessful() else 1


def main(argv=None):
    args = arguments(argv)
    if args.self_test:
        return self_test()
    curl = shutil.which("curl")
    if not curl:
        raise RuntimeError("curl not found. Use the normal Fish launcher to enter guix shell.")
    env = curl_environment()
    version = subprocess.check_output([curl, "-q", "--version"], text=True, env=env, timeout=10)
    match = re.search(r"^curl (\\d+)\\.(\\d+)\\.(\\d+)", version)
    if not match or tuple(map(int, match.groups())) < (7, 70, 0):
        raise RuntimeError("curl >= 7.70 is required for JSON timing output.")
    out_root = args.output_root.expanduser().resolve()
    out_root.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now().strftime("%Y%m%d-%H%M%S-")
    out = Path(tempfile.mkdtemp(prefix=stamp, dir=out_root))
    seed = args.seed if args.seed is not None else random.SystemRandom().randrange(2**32)
    proxies = [k for k in PROXY_KEYS if env.get(k)]
    metadata = {"version": VERSION, "started_utc": utcnow(), "state": "running", "seed": seed,
                "runs": args.runs, "warmup": args.warmup, "pause_s": args.pause, "timeout_s": args.timeout,
                "ip_mode": args.ip, "user_agent": args.user_agent, "sites": list(SITES),
                "python": sys.version, "platform": platform.platform(), "curl_version": version,
                "proxy_variable_names": proxies, "guix_environment": os.environ.get("GUIX_ENVIRONMENT", ""),
                "output": str(out)}
    # Capture channel revisions for repeatability, without pulling or changing channels.
    if shutil.which("guix"):
        try:
            desc = subprocess.run(["guix", "describe", "--format=channels"], stdout=subprocess.PIPE,
                                  stderr=subprocess.PIPE, text=True, timeout=15, check=False)
            if desc.returncode == 0:
                (out / "channels.scm").write_text(desc.stdout, encoding="utf-8")
        except (OSError, subprocess.TimeoutExpired):
            pass
    rows, paused, failures = [], {}, Counter()
    (out / "metadata.json").write_text(json.dumps(metadata, indent=2), encoding="utf-8")
    print(f"SecOps Web Benchmark {VERSION}\\nOutput: {out}\\nMeasured rounds: {args.runs}; warm-up: {args.warmup}; seed: {seed}", flush=True)
    print("HTTP homepage delivery only. No JavaScript, cookie jar, cache-busting, or retries.", flush=True)
    if proxies:
        print("Proxy variables detected; connection-phase breakdown will be omitted. Proxy values are not logged.", flush=True)
    exit_code = 0
    with (out / "results.csv").open("w", encoding="utf-8", newline="") as csvfile, \\
         (out / "diagnostics.jsonl").open("w", encoding="utf-8") as rawfile, \\
         tempfile.TemporaryDirectory(prefix="response-", dir=out) as workdir:
        writer = csv.DictWriter(csvfile, fieldnames=FIELDS, extrasaction="ignore")
        writer.writeheader()
        rng, base = random.Random(seed), list(SITES)
        try:
            for phase, rounds in (("warmup", args.warmup), ("measured", args.runs)):
                for r in range(1, rounds + 1):
                    order = round_order(r, rng, base)
                    for position, (site, url) in enumerate(order, 1):
                        if site in paused:
                            row = {"site": site, "requested_url": url, "started_utc": utcnow(),
                                   "status": "skipped", "reason": paused[site]}
                            diag = {"row": row}
                        else:
                            row, diag = measure(curl, site, url, args, env, proxies, Path(workdir))
                            if row["status"] in ("challenge_detected", "consent_detected", "javascript_required") or row.get("http_code") in (403, 429):
                                paused[site] = row["reason"] + "; remaining requests skipped (no bypass)"
                            elif row["status"] != "eligible":
                                failures[site] += 1
                                if failures[site] >= 3:
                                    paused[site] = "three consecutive ineligible responses; remaining requests skipped"
                            else:
                                failures[site] = 0
                        row.update(phase=phase, round=r, position=position)
                        rows.append(row)
                        writer.writerow({k: safe_csv(row.get(k)) for k in FIELDS})
                        csvfile.flush()
                        rawfile.write(json.dumps(diag, ensure_ascii=False) + "\\n")
                        rawfile.flush()
                        print(f\'{phase:8} {r:02}/{rounds:02} {site:19} HTTP {str(row.get("http_code", "-")):3} {row["status"]:21} TTFB {fmt(row.get("ttfb_ms")):>8} ms  HTML {fmt(row.get("total_ms")):>8} ms\', flush=True)
                        if row["status"] != "skipped":
                            time.sleep(args.pause)
            metadata["state"] = "completed"
        except KeyboardInterrupt:
            metadata["state"], exit_code = "interrupted; partial data", 130
            print("\\nInterrupted. Preserving completed rows and producing a partial report.", flush=True)
        except Exception as exc:
            metadata["state"], exit_code = "failed; partial data", 2
            metadata["failure"] = clean(exc, 1800)
            print("\\nStopped: " + clean(exc), file=sys.stderr)
        finally:
            metadata["finished_utc"] = utcnow()
            metadata["measured_rows"] = sum(r["phase"] == "measured" for r in rows)
            metadata["paused_sites"] = paused
            print("\\n" + report(rows, out, metadata), flush=True)
    if not any(r["phase"] == "measured" and r["status"] == "eligible" for r in rows) and exit_code == 0:
        print("No eligible measured responses; no performance conclusion is possible.", file=sys.stderr)
        exit_code = 2
    return exit_code


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (OSError, RuntimeError, subprocess.SubprocessError) as exc:
        print("ERROR: " + clean(exc, 1800), file=sys.stderr)
        sys.exit(2)
'

if test "$system_deps" -eq 1
    if not command -sq python3
        printf '%s\n' 'ERROR: python3 is required for --system-deps.' >&2
        exit 2
    end
    exec python3 -I -S -c "$program" $argv
end

set -l guix_command ''
if command -sq guix
    set guix_command (command -s guix)
else if test -x "$HOME/.config/guix/current/bin/guix"
    set guix_command "$HOME/.config/guix/current/bin/guix"
else if test -x "$HOME/.guix-profile/bin/guix"
    set guix_command "$HOME/.guix-profile/bin/guix"
else
    printf '%s\n' 'ERROR: Guix was not found on PATH or in the usual user profiles.' >&2
    exit 2
end

exec "$guix_command" shell python curl nss-certs -- python3 -I -S -c "$program" $argv
