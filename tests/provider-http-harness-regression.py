#!/usr/bin/env python3
"""Exercise mock isolation without upstream traffic, with or without ext-curl.

The 15 predeclared-function combinations are userland fixtures, not a substitute
for an ext-curl-enabled runtime check. On that runtime we also check the native
functions explicitly. All PHP invocations are child CLI processes.
"""
import itertools
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import threading
import time
import unittest

ROOT = Path(__file__).resolve().parents[1]
TARGET = ROOT / 'tests/provider-http-regression.php'
FUNCTIONS = ('curl_setopt', 'curl_exec', 'curl_share_init', 'curl_share_setopt')
PHP = shutil.which('php')
DISABLED = ','.join(FUNCTIONS)
NATIVE_BODY = b'bounded connection fixture\n'
NATIVE_REQUEST_LIMIT = 8
NATIVE_SETUP_DELAY = 0.06


class ConnectionFixture(ThreadingHTTPServer):
    """Only eight loopback GETs, with an artificial cost per accepted socket."""
    daemon_threads = True

    def __init__(self):
        self.accepted = 0
        self.requests = []
        self.sockets = []
        self.lock = threading.Lock()
        super().__init__(('127.0.0.1', 0), ConnectionHandler)

    def get_request(self):
        connection, address = super().get_request()
        connection.settimeout(2)
        connection.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)
        with self.lock:
            self.accepted += 1
            self.sockets.append(connection)
            within_limit = self.accepted <= NATIVE_REQUEST_LIMIT
        if not within_limit:
            connection.close()
            raise OSError('Loopback fixture connection limit reached')
        time.sleep(NATIVE_SETUP_DELAY)
        return connection, address


class ConnectionHandler(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def do_GET(self):
        with self.server.lock:
            if len(self.server.requests) >= NATIVE_REQUEST_LIMIT:
                self.send_error(429)
                self.close_connection = True
                return
            self.server.requests.append((self.path, dict(self.headers)))
        self.send_response(200)
        self.send_header('Content-Type', 'text/plain')
        self.send_header('Content-Length', str(len(NATIVE_BODY)))
        self.send_header('Set-Cookie', 'fixture_seen=dummy; Path=/')
        self.end_headers()
        self.wfile.write(NATIVE_BODY)
        self.wfile.flush()

    def log_message(self, *args):
        pass


NATIVE_PHP = r'''
require 'data/config.php'; require 'lib/provider_http.php';
$url=getenv('PROVIDER_HTTP_FIXTURE_URL');
if (parse_url($url,PHP_URL_SCHEME)!=='http' || parse_url($url,PHP_URL_HOST)!=='127.0.0.1') exit(2);
$count=(int)getenv('PROVIDER_HTTP_FIXTURE_REQUESTS');
if ($count<1 || $count>3) exit(2);
$shared=getenv('PROVIDER_HTTP_FIXTURE_MODE')==='shared';
$bodies=[];$started=hrtime(true);
for($i=0;$i<$count;$i++) {
    $handle=curl_init($url.'/fixture/'.$i.($i===0?'?fixture_query=first':''));
    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,
        CURLOPT_PROXY=>'',CURLOPT_NOPROXY=>'*',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
        CURLOPT_CONNECTTIMEOUT_MS=>1000,CURLOPT_TIMEOUT_MS=>2000,CURLOPT_COOKIEFILE=>'']);
    if($i===0) {
        curl_setopt($handle,CURLOPT_COOKIE,'fixture_first=dummy');
        curl_setopt($handle,CURLOPT_HTTPHEADER,['Authorization: Bearer fixture-only','X-Fixture-Private: first-only']);
    }
    $body=$shared?provider_http::exec($handle):curl_exec($handle);
    if($body===false || curl_getinfo($handle,CURLINFO_RESPONSE_CODE)!==200) {
        fwrite(STDERR,'Loopback fixture transfer failed.');exit(1);
    }
    $bodies[]=$body;
    curl_close($handle);unset($handle);
}
echo json_encode(['bodies'=>$bodies,'total_ms'=>(hrtime(true)-$started)/1000000],JSON_THROW_ON_ERROR);
'''


def php(*args):
    return subprocess.run([PHP, *args], cwd=ROOT, capture_output=True, text=True, timeout=15)


class HarnessTests(unittest.TestCase):
    def test_native_request_local_connections_and_isolation(self):
        probe = php('-d', 'disable_functions=', '-r',
                    'echo extension_loaded("curl") ? "native" : "absent";')
        self.assertEqual(probe.returncode, 0, probe.stderr)
        if probe.stdout != 'native':
            self.skipTest('Native ext-curl connection behavior remains a VPS gate.')
        server = ConnectionFixture()
        thread = threading.Thread(target=server.serve_forever, kwargs={'poll_interval': 0.02}, daemon=True)
        thread.start()
        url = 'http://127.0.0.1:' + str(server.server_port)

        def batch(mode, count):
            before_connections, before_requests = server.accepted, len(server.requests)
            result = subprocess.run([PHP, '-d', 'disable_functions=', '-r', NATIVE_PHP],
                                    cwd=ROOT, capture_output=True, text=True, timeout=15,
                                    env={'PROVIDER_HTTP_FIXTURE_URL': url,
                                         'PROVIDER_HTTP_FIXTURE_MODE': mode,
                                         'PROVIDER_HTTP_FIXTURE_REQUESTS': str(count)})
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            self.assertEqual(result.stderr, '')
            output = json.loads(result.stdout)
            self.assertEqual(output['bodies'], [NATIVE_BODY.decode()] * count)
            requests = server.requests[before_requests:]
            self.assertEqual(len(requests), count)
            self.assertEqual(requests[0][0], '/fixture/0?fixture_query=first')
            first_headers = {key.lower(): value for key, value in requests[0][1].items()}
            self.assertIn('fixture_first=dummy', first_headers.get('cookie', ''))
            self.assertEqual(first_headers.get('authorization'), 'Bearer fixture-only')
            self.assertEqual(first_headers.get('x-fixture-private'), 'first-only')
            for index, (path, headers) in enumerate(requests[1:], 1):
                self.assertEqual(path, '/fixture/' + str(index))
                headers = {key.lower(): value for key, value in headers.items()}
                self.assertNotIn('cookie', headers)
                self.assertNotIn('authorization', headers)
                self.assertNotIn('x-fixture-private', headers)
            return server.accepted - before_connections, output['total_ms']

        try:
            baseline_connections, baseline_ms = batch('baseline', 3)
            shared_connections, shared_ms = batch('shared', 3)
            print(json.dumps({'fixture': 'native HTTP/1.1 loopback; artificial connection setup delay',
                              'requests_per_comparison': 3, 'setup_delay_ms': NATIVE_SETUP_DELAY * 1000,
                              'baseline_connections': baseline_connections, 'shared_connections': shared_connections,
                              'baseline_ms': round(baseline_ms, 3), 'shared_ms': round(shared_ms, 3)}))
            self.assertEqual(baseline_connections, 3, 'Fresh baseline handles must open three connections.')
            self.assertEqual(shared_connections, 1, 'Fresh provider handles must reuse one request-local connection.')
            independent_connections, _ = batch('shared', 2)
            self.assertEqual(independent_connections, 1, 'A separate PHP request must open its own connection.')
            self.assertEqual(len(server.requests), NATIVE_REQUEST_LIMIT)
        finally:
            server.shutdown()
            for connection in server.sockets:
                try:
                    connection.shutdown(socket.SHUT_RDWR)
                except OSError:
                    pass
                connection.close()
            server.server_close()
            thread.join(timeout=2)
            self.assertFalse(thread.is_alive(), 'Loopback fixture server must terminate.')

    def test_plain_lint_with_default_runtime(self):
        result = php('-l', str(TARGET))
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_documented_isolated_command(self):
        result = php('-d', 'disable_functions=' + DISABLED, str(TARGET))
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn('PASS: legacy transport caps', result.stdout)

    def test_all_predeclared_function_combinations_lint_and_refuse_execution(self):
        source = TARGET.read_text()
        for count in range(1, len(FUNCTIONS) + 1):
            for functions in itertools.combinations(FUNCTIONS, count):
                with self.subTest(predeclared=functions), tempfile.TemporaryDirectory() as directory:
                    # php -n avoids host extensions. A tripwire makes any call
                    # through these pre-existing functions a hard failure.
                    definitions = '\n'.join(
                        'function ' + name + '(...$args) {'
                        'fwrite(STDERR, "UNEXPECTED_TRANSPORT_CALL\\n"); exit(99);}'
                        for name in functions
                    )
                    path = Path(directory) / 'collision.php'
                    path.write_text('<?php\n' + definitions + '\n?>\n' + source)
                    lint = php('-n', '-l', str(path))
                    self.assertEqual(lint.returncode, 0, lint.stdout + lint.stderr)
                    result = php('-n', str(path))
                    self.assertEqual(result.returncode, 2, result.stdout + result.stderr)
                    self.assertIn('Offline cURL mocks are not isolated:', result.stderr)
                    self.assertNotIn('UNEXPECTED_TRANSPORT_CALL', result.stdout + result.stderr)
                    self.assertNotIn('Cannot redeclare', result.stdout + result.stderr)
                    self.assertNotIn('PASS:', result.stdout)

    def test_default_runtime_mode(self):
        probe = php('-d', 'disable_functions=', '-r',
                    'echo extension_loaded("curl") ? "native" : "absent";')
        self.assertEqual(probe.returncode, 0, probe.stderr)
        result = php('-d', 'disable_functions=', str(TARGET))
        if probe.stdout == 'native':
            self.assertEqual(result.returncode, 2, result.stdout + result.stderr)
            self.assertIn('Offline cURL mocks are not isolated:', result.stderr)
            print('PASS: native ext-curl stays enabled; unsafe test invocation refused.')
        else:
            self.assertEqual(probe.stdout, 'absent')
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            self.assertIn('PASS: legacy transport caps', result.stdout)
            print('INFO: ext-curl absent here; native-extension check remains a VPS gate.')


if __name__ == '__main__':
    if PHP is None:
        raise SystemExit('Required audit interpreter missing: php')
    unittest.main(verbosity=2)
