#!/usr/bin/env python3
"""Exercise the exact shared navigation assertions and the HTTP fixture's order.

No native DOM/cURL/APCu stand-ins are installed. This suite executes real PHP
rendering and pool logic without the extension-dependent full HTTP setup. The
full native regression.php and http-regression.py commands remain mandatory.
"""
import ast
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
ORIGINS = ['https://redlib.privacyredirect.com', 'https://redlib.nadeko.net',
           'https://redlib.privadency.com']


class NewsAuditContract(unittest.TestCase):
    def php(self, code, primary=None, fallbacks=True):
        with tempfile.TemporaryDirectory(prefix='news-config-') as temp:
            config = Path(temp) / 'config.php'
            # Separate test config file: no writes to the real source config.
            text = (ROOT / 'data/config.php').read_text()
            if primary is not None:
                old = "const REDLIB_PRIMARY = 'https://redlib.privacyredirect.com';"
                self.assertIn(old, text)
                text = text.replace(old, 'const REDLIB_PRIMARY = ' + json.dumps(primary) + ';')
            if not fallbacks:
                self.assertIn('const REDLIB_FALLBACKS = true;', text)
                text = text.replace('const REDLIB_FALLBACKS = true;', 'const REDLIB_FALLBACKS = false;')
            config.write_text(text)
            pre = ('require ' + json.dumps(str(config)) + '; require "lib/frontend.php";'
                   'require "tests/navigation-assertions.php"; $_COOKIE=[];$_GET=[];')
            return subprocess.run(['php', '-d', 'allow_url_fopen=0', '-r', pre + code],
                                  cwd=ROOT, text=True, capture_output=True, timeout=8)

    def test_default_navigation(self):
        p = self.php('assert_external_navigation(new frontend());echo "navigation ok";')
        self.assertEqual(p.returncode, 0, p.stderr)
        self.assertIn('navigation ok', p.stdout)

    def test_every_approved_primary_with_and_without_fallback(self):
        for origin in ORIGINS:
            for fallback in (False, True):
                with self.subTest(origin=origin, fallback=fallback):
                    p = self.php('assert_external_navigation(new frontend());echo service_pool::primary();',
                                 origin, fallback)
                    self.assertEqual(p.returncode, 0, p.stderr)
                    self.assertEqual(p.stdout, origin)

    def test_retired_link_and_wrong_primary_remain_failures(self):
        for template in ('home.html', 'header.html'):
            for wrong in ('https://libre.securityops.co', ORIGINS[1]):
                with self.subTest(template=template, wrong=wrong):
                    code = '''$f=new class extends frontend {
                      public function load($template,$replacements=[]) {
                        $html=parent::load($template,$replacements);
                        return $template===''' + json.dumps(template) + ''' ?
                          str_replace(service_pool::primary(),''' + json.dumps(wrong) + ''',$html) : $html;
                      }}; assert_external_navigation($f);'''
                    p = self.php(code)
                    self.assertNotEqual(p.returncode, 0)
                    self.assertIn('Reddit navigation must use the effective primary', p.stderr)

    def test_full_native_suite_calls_shared_assertions(self):
        source = (ROOT / 'tests/regression.php').read_text()
        self.assertIn("require_once __DIR__ . '/navigation-assertions.php';", source)
        self.assertIn('assert_external_navigation($frontend);', source)
        self.assertNotIn("'Reddit'=>'libre'", source)

    def test_http_failure_expectation_matches_actual_pool_order_and_cooldown(self):
        # Read the actual assertion, not a manually maintained duplicate in this test.
        tree = ast.parse((ROOT / 'tests/http-regression.py').read_text())
        matches = [node for node in ast.walk(tree) if isinstance(node, ast.Assert)
                   and isinstance(node.test, ast.Compare)
                   and isinstance(node.test.left, ast.Name) and node.test.left.id == 'attempts']
        self.assertEqual(len(matches), 1)
        expected = ast.literal_eval(matches[0].test.comparators[0])
        self.assertEqual(expected, ORIGINS)
        code = r'''
        $calls=[]; $cache=[];
        $read=function($key)use(&$cache){return $cache[$key]??false;};
        $write=function($key,$value,$ttl)use(&$cache){$cache[$key]=$value;};
        $attempt=function($origin,$deadline)use(&$calls){$calls[]=$origin;throw new RuntimeException('offline fixture');};
        for($i=0;$i<2;$i++) {
            try{service_pool::run(service_pool::origins(),$attempt,$read,$write);exit(90);}
            catch(RuntimeException $expected){}
        }
        echo json_encode($calls);
        '''
        p = self.php(code)
        self.assertEqual(p.returncode, 0, p.stderr)
        self.assertEqual(json.loads(p.stdout), expected)
        self.assertEqual(len(set(expected)), 3)

    def test_all_previous_commands_and_timeouts_remain(self):
        script = (ROOT / 'scripts/test.sh').read_text()
        commands = [line for line in script.splitlines() if line.startswith('run_test ')]
        self.assertEqual(len(commands), 61)
        self.assertIn('run_test php -d apc.enable_cli=1 tests/regression.php', commands)
        self.assertIn('run_test python3 tests/http-regression.py', commands)
        source = (ROOT / 'tests/http-regression.py').read_text()
        self.assertIn('urllib.request.urlopen(req,timeout=8)', source)
        self.assertIn("elapsed<3", source)
        self.assertIn("'OFFLINE_NETWORK_ATTEMPT'", source)
        self.assertIn('code==503', source)


if __name__ == '__main__':
    unittest.main(verbosity=2)
