#!/usr/bin/env python3
"""Actual PHP RSS parsing/rendering under offline transports and network tripwires."""
import json, os, shutil, signal, socket, subprocess, tempfile, time, urllib.error, urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
DISABLED='curl_exec,curl_multi_exec,dns_get_record,gethostbynamel,gethostbyname,fsockopen,pfsockopen,stream_socket_client,socket_connect'

def main():
    native=subprocess.run(['php','-r','exit(class_exists("DOMDocument") && function_exists("mb_strcut") && function_exists("apcu_fetch") ? 0 : 2);'])
    if native.returncode:
        raise SystemExit('NATIVE_RUNTIME_MISSING: DOM/mbstring/APCu for RSS HTTP suite')
    checks=0
    def check(ok,label):
        nonlocal checks
        checks+=1
        if not ok:raise AssertionError(label)
    with tempfile.TemporaryDirectory(prefix='rss-http-')as temp:
        root=Path(temp)
        for folder in ['lib','template','oracles','api']:shutil.copytree(ROOT/folder,root/folder)
        for folder in ['static','banner']:(root/folder).symlink_to(ROOT/folder,target_is_directory=True)
        (root/'data').mkdir();shutil.copy2(ROOT/'data/config.php',root/'data/config.php')
        for file in ['index.php','settings.php','news.php']:shutil.copy2(ROOT/file,root/file)
        (root/'scraper').mkdir()
        s=(ROOT/'scraper/newswire.php').read_text().replace('class newswire {','class newswire_adapter {')
        s+='''
class newswire extends newswire_adapter {
 protected function fetch(string $source,string $query,string $market,int $deadline): string {
  $calls=apcu_fetch('rss-fixture-calls') ?: []; $calls[]=[$source,$query,$market];apcu_store('rss-fixture-calls',$calls);
  if($query==='blocked' || ($query==='fallback' && $source==='google')) throw new news_failure('http_refused',418);
  if($query==='challenge') return '<html><script id="anubis_challenge"></script></html>';
  $items=$query==='empty' ? '' : '<item><title>&lt;script&gt;bad()&lt;/script&gt; Fixture '.$source.' &amp; news</title><link>https://example.org/story</link><source>Publisher &amp; Co</source><description>&lt;img src="https://private.invalid/"&gt;</description></item>';
  return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Offline fixture</title>'.$items.'</channel></rss>';
 }
}
'''
        (root/'scraper/newswire.php').write_text(s)
        shutil.copy2(ROOT/'tests/fixtures/offline-network.php',root/'offline-network.php')
        (root/'router.php').write_text('''<?php
require __DIR__.'/offline-network.php';
$p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($p==='/reset'){apcu_clear_cache();echo 'reset';return true;}
if($p==='/stats'){header('Content-Type: application/json');echo json_encode(apcu_fetch('rss-fixture-calls') ?: []);return true;}
if($p==='/'){require 'index.php';return true;}
if($p==='/news'){require 'news.php';return true;}
if($p==='/api/v1/news'){chdir(__DIR__.'/api/v1');require 'news.php';return true;}
http_response_code(404);return true;
''')
        with socket.socket()as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
        env=dict(os.environ);env.pop('PHP_CLI_SERVER_WORKERS',None)
        with (root/'server.log').open('w+')as log:
            server=subprocess.Popen(['php','-d','disable_functions='+DISABLED,'-d','allow_url_fopen=0','-d','apc.enable_cli=1','-d','display_errors=0','-d','log_errors=1','-d','error_log=/dev/stderr','-S',f'127.0.0.1:{port}','router.php'],cwd=root,env=env,stdout=log,stderr=log,start_new_session=True)
            def request(path):
                req=urllib.request.Request(f'http://127.0.0.1:{port}'+path,headers={'User-Agent':'Mozilla/5.0 fixture'})
                try:r=urllib.request.urlopen(req,timeout=8)
                except urllib.error.HTTPError as e:r=e
                with r:return r.status,r.headers,r.read().decode()
            def calls():return json.loads(request('/stats')[2])
            try:
                for i in range(100):
                    try:
                        if request('/reset')[0]==200:break
                    except OSError:time.sleep(.04)
                else:raise RuntimeError('PHP server failed to start')
                code,headers,body=request('/news');check(code==200,'default news route');check('Google News RSS' in body,'source attribution');check('Publisher &amp; Co' in body,'publisher escaped');check('<script>' not in body,'no raw upstream script');check('private.invalid' not in body,'no upstream HTML/images');check('class="nextpage' not in body,'no fabricated pagination');check(headers['Cache-Control']=='private, no-store','private response headers');check("script-src 'none'" in headers['Content-Security-Policy'],'ordinary RSS no JS');check(len(calls())==1,'one public fetch')
                code,headers,body=request('/news');check('public headline cache' in body and len(calls())==1,'repeat public feed cached')
                request('/news?s=technology');request('/news?s=technology');check(len(calls())==3,'keyword searches not cached')
                request('/reset');code,_,body=request('/news?s=fallback');check(code==200 and 'Bing News RSS' in body,'actual fallback identified');check([x[0]for x in calls()]==['google','bing'],'at most two sources')
                request('/news?s=technology');check([x[0]for x in calls()]==['google','bing','bing'],'refusal cooldown skips first source')
                request('/reset');code,_,body=request('/news?s=empty');check(code==200 and 'No news found' in body and len(calls())==1,'empty valid no fallback')
                request('/reset');code,_,body=request('/news?s=blocked&source=google');check(code==503 and len(calls())==1,'explicit source failure not replaced')
                request('/reset');code,_,body=request('/news?s=challenge');check(code==503 and len(calls())==2,'challenge never results')
                request('/reset');code,_,body=request('/news?source=bing&market=pt-BR');check(code==200 and calls()==[['bing','','pt-BR']],'edition/source retained')
                request('/reset');code,headers,body=request('/api/v1/news?s=technology');data=json.loads(body);check(code==200 and len(data.get('news',[]))==1,'API parsed RSS');check(data.get('npt')is None,'API no fake continuation');check(headers['Cache-Control']=='private, no-store','API success never shared-cacheable')
                print(f'PASS: {checks} actual PHP RSS/API/header/cache tests, offline transport; no live source requests.')
            except Exception:
                log.flush();log.seek(0);print(log.read()[-10000:]);raise
            finally:
                os.killpg(server.pid,signal.SIGTERM)
                try:server.wait(timeout=5)
                except subprocess.TimeoutExpired:os.killpg(server.pid,signal.SIGKILL);server.wait()
if __name__=='__main__':main()
