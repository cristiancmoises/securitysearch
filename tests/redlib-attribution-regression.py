#!/usr/bin/env python3
"""Actual local PHP rendering of operator notices. No external provider requests."""
import html
from html.parser import HTMLParser
import json
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
NOTICE = ('The external Redlib instances used or linked by SecuritySearch are provided '
          'and operated by independent third-party individuals or organizations, not by Security Ops.')
SHORT = 'External Redlib instances are operated by independent third parties, not Security Ops.'
ORIGINS = ['https://redlib.privacyredirect.com', 'https://redlib.nadeko.net', 'https://redlib.privadency.com']

class Document(HTMLParser):
    def __init__(self, source):
        super().__init__(convert_charrefs=True)
        self.sections=[]; self.external_links=[]; self.links=[]; self.scripts=[]; self.text=[]
        self.feed(source)
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if tag=='section': self.sections.append(a)
        if tag=='a':
            self.links.append(a)
            if any('external-providers' in s.get('class','').split() for s in self.sections): self.external_links.append(a)
        if tag=='script': self.scripts.append(a)
    def handle_endtag(self, tag):
        if tag=='section' and self.sections: self.sections.pop()
    def handle_data(self, data): self.text.append(data)

class Attribution(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp=tempfile.TemporaryDirectory(prefix='redlib-attribution-http-')
        router=Path(cls.temp.name)/'router.php'
        router.write_text('''<?php
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
chdir('''+json.dumps(str(ROOT))+''');
$path=parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/') { require 'index.php'; return true; }
if ($path === '/about') { require 'about.php'; return true; }
if ($path === '/settings') { require 'settings.php'; return true; }
if ($path !== '/reddit-error' && $path !== '/reddit-results') { http_response_code(404); return true; }
ob_start(); // The production news controller buffers its header until status is known.
require 'lib/security_headers.php'; require 'data/config.php'; require 'lib/frontend.php';
$f=new frontend();
$get=['s'=>'offline attribution check','scraper'=>'reddit','nsfw'=>'yes'];
$filters=['scraper'=>['display'=>'Provider','option'=>['reddit'=>'Reddit via Redlib (third-party; optional)']]];
$f->loadheader($get,$filters,'news');
if ($path === '/reddit-error') { $f->drawscrapererror('Offline refusal fixture', $get, 'news'); return true; }
echo $f->load('search.html',['timetaken'=>null,'class'=>'','right-left'=>'','right-right'=>'','left'=>'<p>Local result fixture, not a provider request.</p>']);
return true;
''')
        with socket.socket() as s:
            s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
        cls.base='http://127.0.0.1:'+str(port)
        cls.log=tempfile.TemporaryFile()
        cls.server=subprocess.Popen(['php','-d','allow_url_fopen=0','-d',
            'disable_functions=curl_exec,curl_multi_exec,dns_get_record,gethostbynamel,gethostbyname,fsockopen,pfsockopen,stream_socket_client,socket_connect',
            '-S','127.0.0.1:'+str(port),str(router)], cwd=ROOT,stdout=cls.log,stderr=cls.log)
        cls.client=urllib.request.build_opener(urllib.request.ProxyHandler({}))
        for _ in range(80):
            try:
                with cls.client.open(cls.base,timeout=1) as r:r.read()
                return
            except OSError: time.sleep(.05)
        cls.server.terminate();cls.server.wait(timeout=5)
        raise RuntimeError('Local PHP server failed to start')
    @classmethod
    def tearDownClass(cls):
        cls.server.terminate();cls.server.wait(timeout=5);cls.log.close();cls.temp.cleanup()
    def page(self, path='/', cookie=None):
        req=urllib.request.Request(self.base+path,headers={'User-Agent':'Mozilla/5.0 Local attribution fixture',**({'Cookie':cookie} if cookie else {})})
        try:r=self.client.open(req,timeout=5)
        except urllib.error.HTTPError as e:r=e
        with r:return r.status,dict(r.headers),r.read().decode('utf-8')
    def test_home_groups_redlib_separately(self):
        code,headers,body=self.page();self.assertEqual(code,200)
        self.assertIn('Explore services and external providers',body)
        self.assertNotIn('Explore SecurityOps services',body)
        self.assertIn(NOTICE,html.unescape(body))
        p=Document(body)
        self.assertTrue(any(a.get('href')==ORIGINS[0] for a in p.external_links))
        self.assertFalse(p.scripts)
        self.assertIn("script-src 'none'",headers['Content-Security-Policy'])
    def test_visible_footer_is_not_only_tooltip(self):
        _,_,body=self.page();p=Document(body)
        self.assertIn(SHORT,''.join(p.text))
        self.assertIn('data-attribution-revision="redlib-attribution-1"',body)
        self.assertTrue(any(a.get('href')=='/about#external-redlib' for a in p.links))
    def test_about_operator_boundary_and_rss_distinction(self):
        code,_,body=self.page('/about');self.assertEqual(code,200)
        for text in (NOTICE,'not those external Redlib deployments','does not use Redlib','subject to its operator'):
            self.assertIn(text,html.unescape(body))
        self.assertIn('id="external-redlib"',body)
    def test_settings_labels_and_private_policy(self):
        code,h,b=self.page('/settings');self.assertEqual(code,200)
        self.assertIn(SHORT,html.unescape(b));self.assertIn('Reddit via Redlib (third-party)',b)
        self.assertIn('private, no-store',h['Cache-Control']);self.assertFalse(Document(b).scripts)
    def test_result_and_failure_disclose_external_operator(self):
        for path,status in (('/reddit-results',200),('/reddit-error',503)):
            with self.subTest(path=path):
                code,h,b=self.page(path);self.assertEqual(code,status)
                self.assertIn(NOTICE,html.unescape(b));self.assertIn(SHORT,html.unescape(b))
                self.assertIn('may receive the query',b);self.assertNotIn('{%redlib_',b)
                self.assertIn('private, no-store',h['Cache-Control']);self.assertFalse(Document(b).scripts)
    def test_saved_themes_still_show_notice(self):
        for theme in ['Black','Tron','Lain','SecOps','Custom']:
            with self.subTest(theme=theme):
                code,_,b=self.page(cookie='theme='+theme);self.assertEqual(code,200)
                self.assertIn(SHORT,html.unescape(b))
                self.assertIn('data-home-style="black"' if theme=='Black' else '/themes/'+theme+'.css?v30',b)
    def test_disclosure_all_origins_and_fallback_states(self):
        for origin in ORIGINS:
            for enabled in (True,False):
                code='class config {const REDLIB_PRIMARY='+json.dumps(origin)+';const REDLIB_FALLBACKS='+('true'if enabled else 'false')+';}require "lib/service_pool.php";echo json_encode([service_pool::primary(),service_pool::origins(),service_pool::disclosure()]);'
                p=subprocess.run(['php','-d','allow_url_fopen=0','-r',code],cwd=ROOT,capture_output=True,text=True,timeout=5)
                self.assertEqual(p.returncode,0,p.stderr);primary,origins,text=json.loads(p.stdout)
                self.assertEqual(primary,origin);self.assertEqual(origins[0],origin)
                self.assertEqual(len(origins),3 if enabled else 1);self.assertIn(NOTICE,text)
                self.assertIn(origin.split('//')[1],text)
                self.assertIn('may receive the query' if enabled else 'fallback is disabled',text)
    def test_documentation_bilingual_and_tags_unchanged(self):
        en=(ROOT/'README.md').read_text();pt=(ROOT/'README.pt-BR.md').read_text()
        self.assertIn('not by Security Ops',en);self.assertIn('não pela Security Ops',pt)
        self.assertIn('published\nv0.9.24 tag',en)
        self.assertEqual((ROOT/'data/release-version.txt').read_text().strip(),'0.9.26')
    def test_runner_enforces_notice_without_removing_existing_gates(self):
        commands=[l for l in (ROOT/'scripts/test.sh').read_text().splitlines() if l.startswith('run_test ')]
        self.assertEqual(len(commands),67)
        self.assertEqual(commands[56],'run_test python3 tests/redlib-attribution-regression.py')
        for name in ('tests/native-runtime.php','tests/http-regression.py','tests/newswire-regression.php'):
            self.assertTrue(any(name in l for l in commands))
        s=(ROOT/'scripts/deploy-ionos.py').read_text()
        self.assertIn('live_news_gate(',s);self.assertIn('live_binternet_gate(',s)
    def test_no_new_remote_resources_or_upload_behavior(self):
        source=(ROOT/'lib/redlib_attribution.php').read_text()
        for forbidden in ('curl_exec','file_get_contents','fetch(','<script','<iframe'):
            self.assertNotIn(forbidden,source)
        self.assertNotIn("operator's Redlib frontend",(ROOT/'scraper/reddit.php').read_text())

if __name__=='__main__':unittest.main(verbosity=2)
