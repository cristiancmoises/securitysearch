#!/usr/bin/env python3
"""Real localhost HTTP tests of production frontend and music buffering prologue.
Not a full music-controller or provider test; that remains in http-regression.py.
"""
import json
import os
from pathlib import Path
import signal
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser
R = Path(__file__).resolve().parents[1]
class Links(HTMLParser):
    def __init__(self, body):
        super().__init__(); self.links = []; self.feed(body)
    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            value = dict(attrs).get('href')
            if value: self.links.append(value)
class HTTPState(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix='securitysearch-state-')
        cls.root = Path(cls.temp.name)
        # Take exactly the production prologue: no replacement implementation.
        music = (R/'music.php').read_text().split('/*', 1)[0].split('<?php', 1)[1]
        music = music.replace('__DIR__', json.dumps(str(R)))
        (cls.root/'router.php').write_text('''<?php
chdir(''' + json.dumps(str(R)) + ''');
require 'data/config.php';require 'lib/frontend.php';$f=new frontend();
$path=parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if($path==='/ping'){echo 'ready';return;}
if($path==='/next'){header('Content-Type: application/json');echo json_encode(['next'=>$f->htmlnextpage(['s'=>$_GET['s']??'0','nsfw'=>'0'],$_GET['token']??'','images')]);return;}
if($path==='/recovery'){echo $f->provider_recovery('fixture <script>', ['s'=>$_GET['s']??'0','nsfw'=>'0','scraper'=>'google','npt'=>'old'], 'images')[1];return;}
if($path==='/music-error'){''' + music + '''
header('Refresh: 1');echo str_repeat(' ',16384).'<header>Fixture header already rendered</header>';
$f->drawscrapererror('fixture unavailable', ['s'=>'0','nsfw'=>'0','scraper'=>'sc'], 'music');return;}
http_response_code(404);
''')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
        cls.base = f'http://127.0.0.1:{port}'
        cls.log = (cls.root/'server.log').open('w+')
        cls.process = subprocess.Popen(['php','-n','-d','output_buffering=0','-d','display_errors=0','-d','log_errors=1','-d','error_reporting=-1','-d','allow_url_fopen=0','-d','disable_functions=fsockopen,pfsockopen,stream_socket_client,socket_connect,curl_exec,curl_multi_exec','-S',f'127.0.0.1:{port}',str(cls.root/'router.php')],cwd=R,stdout=cls.log,stderr=cls.log,start_new_session=True)
        try:
            for _ in range(100):
                try:
                    if cls.request('/ping')[0] == 200: break
                except OSError: time.sleep(.03)
            else: raise RuntimeError('Local fixture failed to start')
        except BaseException:
            cls.tearDownClass(); raise
    @classmethod
    def tearDownClass(cls):
        if cls.process.poll() is None:
            os.killpg(cls.process.pid, signal.SIGTERM); cls.process.wait(timeout=5)
        cls.log.close(); cls.temp.cleanup()
    @classmethod
    def request(cls, path):
        try: response = urllib.request.urlopen(cls.base + path, timeout=5)
        except urllib.error.HTTPError as error: response = error
        with response: return response.status, response.headers, response.read().decode('utf-8')
    def next(self, term, token):
        status, headers, body = self.request('/next?' + urllib.parse.urlencode({'s':term,'token':token}))
        self.assertEqual(status, 200); self.assertIn('application/json', headers['Content-Type'])
        url = json.loads(body)['next']; self.assertFalse(urllib.parse.urlsplit(url).fragment)
        return urllib.parse.parse_qs(urllib.parse.urlsplit(url).query, keep_blank_values=True)
    def test_literal_zero(self): self.assertEqual(self.next('0','valid'), {'s':['0'],'nsfw':['0'],'npt':['valid']})
    def test_all_and_any(self):
        for term in ('all','any'): self.assertEqual(self.next(term,'valid')['s'],[term])
    def test_unicode_and_symbols(self):
        term='ação 日本語 & C++';self.assertEqual(self.next(term,'+/%#&s=x'),{'s':[term],'nsfw':['0'],'npt':['+/%#&s=x']})
    def test_token_cannot_replace_filter(self): self.assertEqual(self.next('0','x&nsfw=1')['nsfw'],['0'])
    def test_existing_backend_token(self):
        token='brave.'+'a'*43; self.assertEqual(self.next('0',token)['npt'],[token])
    def test_recovery_links_and_escaping(self):
        status,_,body=self.request('/recovery?s=any');self.assertEqual(status,200);self.assertNotIn('<script>',body)
        links=[u for u in Links(body).links if u!='/settings'];self.assertTrue(links)
        for url in links:
            p=urllib.parse.parse_qs(urllib.parse.urlsplit(url).query);self.assertEqual(p['s'],['any']);self.assertEqual(p['nsfw'],['0']);self.assertNotIn('npt',p)
    def test_music_prologue_preserves_error_status(self):
        status,headers,body=self.request('/music-error');self.assertEqual(status,503);self.assertEqual(headers['Cache-Control'],'private, no-store');self.assertEqual(headers['Retry-After'],'30');self.assertNotIn('Refresh',headers);self.assertIn('Fixture header already rendered',body);self.assertIn('Search paused',body)
    def test_music_recovery_retains_query(self):
        _,_,body=self.request('/music-error');links=Links(body).links
        retry=next(u for u in links if u.startswith('/music?'));p=urllib.parse.parse_qs(urllib.parse.urlsplit(retry).query);self.assertEqual(p['s'],['0']);self.assertEqual(p['nsfw'],['0'])
    def test_no_php_runtime_diagnostics(self):
        self.request('/music-error');self.log.flush();self.log.seek(0);text=self.log.read()
        for marker in ('PHP Warning','PHP Fatal','PHP Deprecated','PHP Parse'):self.assertNotIn(marker,text)
if __name__=='__main__': unittest.main(verbosity=2)
