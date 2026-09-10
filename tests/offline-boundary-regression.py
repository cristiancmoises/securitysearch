#!/usr/bin/env python3
"""Exercise the real Reddit request/pool and fixture boundary without ext-DOM.
Only decoding and continuation persistence are stubbed here; the complete HTTP/DOM
suite remains mandatory in the native audit. Tripwires replace child CLI functions.
"""
import pathlib, subprocess, tempfile, shutil, unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
TARGETS='curl_exec,curl_multi_exec,dns_get_record,gethostbynamel,gethostbyname,fsockopen,pfsockopen,stream_socket_client,socket_connect'
class Boundary(unittest.TestCase):
    def php(self,code):
        return subprocess.run(['php','-d','disable_functions='+TARGETS,'-d','allow_url_fopen=0','-r',code],cwd=ROOT,capture_output=True,text=True,timeout=5)
    def test_each_tripwire_fails_immediately(self):
        for name in TARGETS.split(','):
            with self.subTest(name=name):
                p=self.php("require 'tests/fixtures/offline-network.php';"+name+"();")
                self.assertNotEqual(p.returncode,0);self.assertIn('OFFLINE_NETWORK_ATTEMPT',p.stderr+p.stdout)
    def test_lint_with_default_runtime(self):
        for path in ('tests/fixtures/offline-network.php','tests/fixtures/reddit-http.php'):
            self.assertEqual(subprocess.run(['php','-l',path],cwd=ROOT,capture_output=True).returncode,0)
    def test_all_fallbacks_intercepted(self):
        with tempfile.TemporaryDirectory() as d:
            path=pathlib.Path(d)/'reddit-adapter.php'
            # Replace require lines explicitly, without altering the adapter logic.
            source=(ROOT/'scraper/reddit.php').read_text().replace("__DIR__ . '/../lib/service_search.php'",repr(str(ROOT/'lib/service_search.php'))).replace("__DIR__ . '/../lib/service_pool.php'",repr(str(ROOT/'lib/service_pool.php'))).replace('class reddit extends','class reddit_adapter extends')
            path.write_text(source)
            code="require 'tests/fixtures/offline-network.php';require "+repr(str(path))+";require 'tests/fixtures/reddit-http.php';"+r'''
class decoded_fixture extends reddit {
 public function __construct(){}
 public function decode(string $body,string $origin=self::ORIGIN):array {
  if(str_contains($body,'id="error"'))throw new RuntimeException('fixture blocked');
  return ['news'=>[['title'=>'fixture','url'=>$origin]],'after'=>null,'npt'=>null];
 }
}
chdir('tests/fixtures');copy('redlib-news.html','redlib-fixture-temporary.html');
'''
            # Fixture reads redlib-fixture.html from the disposable directory.
            shutil.copy2(ROOT/'tests/fixtures/redlib-news.html',pathlib.Path(d)/'redlib-fixture.html')
            code=code[:code.index("chdir('tests/fixtures')")]+"chdir("+repr(d)+");"+r'''
$f=new decoded_fixture();$start=hrtime(true);
try{$f->news(['s'=>'failure']);throw new Error('Failure accepted');}catch(RuntimeException $expected){}
if($f->fixture_calls!==service_pool::origins())throw new Error('Missing origin interception');
if(hrtime(true)-$start>1000000000)throw new Error('Offline fixture waited');
$f=new decoded_fixture();$r=$f->news(['s'=>'fallback news']);
if($r['_service']!=='https://redlib.nadeko.net'||count($f->fixture_calls)!==2)throw new Error('Fallback fixture routing');
echo 'all origins intercepted';
'''
            p=self.php(code);self.assertEqual(p.returncode,0,p.stderr+p.stdout);self.assertIn('all origins',p.stdout)
    def test_primary_only_override_exposes_fallback(self):
        # Matches the old bug: primary fixture returns; fallback native DNS escapes.
        code="require 'tests/fixtures/offline-network.php';require 'lib/service_pool.php'; service_pool::run(service_pool::origins(),function($origin,$deadline){if($origin===service_pool::PRIMARY)throw new RuntimeException('blocked fixture');dns_get_record('example.org',DNS_A);});"
        p=self.php(code);self.assertNotEqual(p.returncode,0);self.assertIn('OFFLINE_NETWORK_ATTEMPT',p.stderr+p.stdout)
if __name__=='__main__':unittest.main(verbosity=2)
