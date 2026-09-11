#!/usr/bin/env python3
"""Exercise the real news policy without ctype, and preserve parser test identity.

No substitute PHP extension, provider transport, or decoder is installed. The
full native Reddit and RSS suites remain mandatory in scripts/test.sh.
"""
import importlib.util
import io
import contextlib
import json
from pathlib import Path
import re
import subprocess
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
PHP = ['php', '-d', 'display_errors=stderr', '-d', 'log_errors=0']


class NewsAuditRuntime(unittest.TestCase):
    def php(self, code, *, minimal=False):
        args = (['php', '-n', '-d', 'disable_functions=ctype_digit'] if minimal else PHP)
        return subprocess.run(args + ['-r', code], cwd=ROOT, text=True,
                              capture_output=True, timeout=10)

    def selection_block(self):
        source = (ROOT / 'tests/reddit-regression.php').read_text()
        begin = source.index('$_GET=[];$_COOKIE=[];[')
        end = source.index("verify(count($provider->decode", begin)
        return source[begin:end]

    def test_default_selection_keeps_reddit_fixture_identity(self):
        # Execute the selection assertion in the actual regression, not a copy of
        # the expected selection. No DOM method or external request is invoked.
        code = ('require "data/config.php";require "lib/frontend.php";'
                'require "scraper/reddit.php";'
                'function verify($ok,$label){if(!$ok)throw new RuntimeException($label);}'
                '$provider=new reddit();$saved=$provider;')
        code += self.selection_block()
        code += ('if($provider!==$saved || !($provider instanceof reddit))exit(91);'
                 'if(!($default_provider instanceof newswire))exit(92);'
                 'echo "Reddit identity retained; RSS selected separately";')
        result = self.php(code)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('Reddit identity retained', result.stdout)

    def test_rss_decoder_visibility_is_not_relaxed(self):
        result = self.php('require "data/config.php";require "lib/frontend.php";'
                          'require "scraper/newswire.php";'
                          '$m=new ReflectionMethod(newswire::class,"decode");'
                          'echo $m->isProtected()?"protected":"changed";')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(result.stdout, 'protected')

    def test_success_banner_follows_every_reddit_assertion(self):
        lines = (ROOT / 'tests/reddit-regression.php').read_text().rstrip().splitlines()
        self.assertTrue(lines[-1].startswith('echo "PASS: Redlib source-contract'))
        source = '\n'.join(lines)
        self.assertIn("'Unicode permalink retained safely'", source)
        self.assertIn("'Empty feed container'", source)
        self.assertIn("'Reddit URL prefix stays a search'", source)
        self.assertIn("'Default independent RSS news'", source)

    def test_all_original_policy_assertions_without_ctype(self):
        code = ('if(function_exists("ctype_digit"))exit(90);'
                'require "tests/news-http-policy-regression.php";')
        result = self.php(code, minimal=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('PASS: 47 response-policy assertions', result.stdout)

    def test_ascii_delta_seconds_boundaries_without_ctype(self):
        pairs = [('', 0), ('0', 0), ('1', 1), ('0000900', 900), ('3599', 3599),
                 ('3600', 3600), ('3601', 3600), ('9' * 80, 3600),
                 ('9' * 81, 0), ('-1', 0), ('+1', 0), (' 1', 0), ('1 ', 0),
                 ('1\n', 0), ('1\r\n', 0), ('1\x00', 0), ('1.5', 0), ('1e3', 0),
                 ('\u0661\u0662', 0), ('\uff11\uff12', 0), ('x', 0)]
        cases = json.dumps(pairs, ensure_ascii=True)
        code = ('require "lib/news_http.php";$pairs=json_decode(' + json.dumps(cases) + ',true);'
                'foreach($pairs as [$input,$expected]){'
                'if(news_http::retry_after($input)!==$expected)exit(91);}'
                'echo count($pairs);')
        result = self.php(code, minimal=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(int(result.stdout), len(pairs))

    def test_http_date_bounds_without_ctype(self):
        code = r'''require "lib/news_http.php";
        $now=time();
        $future=gmdate('D, d M Y H:i:s \G\M\T',$now+900);
        $delay=news_http::retry_after($future);
        if($delay<890 || $delay>900)exit(91);
        if(news_http::retry_after(gmdate('D, d M Y H:i:s \G\M\T',$now-60))!==0)exit(92);
        if(news_http::retry_after(gmdate('D, d M Y H:i:s \G\M\T',$now+86400))!==3600)exit(93);
        foreach(['tomorrow','+900 seconds','10 Sep 2026','X']as$value)
            if(news_http::retry_after($value)!==0)exit(94);
        echo 'HTTP dates remain bounded';'''
        result = self.php(code, minimal=True)
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_response_body_and_header_errors_stay_rejections(self):
        code = r'''require "lib/news_http.php";
        foreach([418=>'http_refused',429=>'rate_limited',302=>'redirect',503=>'http_error']as$status=>$reason){
          try{news_http::response(['http'=>['code'=>$status],'headers'=>['retry-after'=>'900']]);exit(91);}
          catch(news_failure $e){if($e->reason!==$reason || $e->http_status!==$status || $e->retry_after!==900)exit(92);}
        }
        echo 'Refusals unchanged';'''
        result = self.php(code, minimal=True)
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_docker_audit_merges_streams_before_running_tests(self):
        spec = importlib.util.spec_from_file_location('news_runtime_deployer', ROOT / 'scripts/deploy-ionos.py')
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        calls = []
        def run(*args, capture=False):
            calls.append(args)
            return 'a' * 64 if args[:2] == ('docker', 'create') else ''
        with tempfile.TemporaryDirectory() as temp, \
                patch.object(module, 'run', side_effect=run), \
                patch.object(module, 'inspect', return_value={'State': {'ExitCode': 0}}), \
                patch.object(module.subprocess, 'run', return_value=subprocess.CompletedProcess([], 0, 'offline fixture')), \
                contextlib.redirect_stdout(io.StringIO()):
            module.offline_audit('offline-fixture-image', Path(temp))
        create = next(call for call in calls if call[:2] == ('docker', 'create'))
        self.assertEqual(create[-1], 'exec 2>&1; sh scripts/test.sh --keep-going')
        self.assertIn('none', create)
        # Run the same redirection in a real local shell, not a Docker simulation.
        result = subprocess.run(['sh', '-c', "exec 2>&1; printf 'first\\n'; printf 'fatal\\n' >&2; printf 'last\\n'"],
                                capture_output=True, text=True, timeout=5)
        self.assertEqual(result.stdout, 'first\nfatal\nlast\n')
        self.assertEqual(result.stderr, '')

    def test_mandatory_native_suites_and_gates_remain(self):
        commands = [line for line in (ROOT / 'scripts/test.sh').read_text().splitlines()
                    if line.startswith('run_test ')]
        self.assertEqual(len(commands), 70)
        self.assertIn('run_test php -d apc.enable_cli=1 tests/reddit-regression.php', commands)
        self.assertIn('run_test php tests/news-http-policy-regression.php', commands)
        self.assertIn('run_test php tests/native-runtime.php', commands)
        deploy = (ROOT / 'scripts/deploy-ionos.py').read_text()
        self.assertIn('if result.returncode != 0 or code != 0:', deploy)
        self.assertIn('live_news_gate(', deploy)
        self.assertIn('live_binternet_gate(', deploy)


if __name__ == '__main__':
    unittest.main(verbosity=2)
