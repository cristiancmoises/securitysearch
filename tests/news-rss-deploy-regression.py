#!/usr/bin/env python3
"""Actual gate validation; Docker calls are mocked, never live-source approval."""
import contextlib, copy, importlib.util, io, json, subprocess, tempfile, unittest
from pathlib import Path
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('rss_gate',ROOT/'scripts/deploy-ionos.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def good(source='bing',market='en-US'):
    return dict(status='ok',provider='newswire',source=source,market=market,attempts=[dict(source=source,status='ok',feed_count=7,search_count=4,stages={'feed':{'status':'ok','count':7},'search':{'status':'ok','count':4}})])
class RSSGate(unittest.TestCase):
    def check(self,report):
        with tempfile.TemporaryDirectory()as t,patch.object(m,'run',side_effect=['',json.dumps(report)]),contextlib.redirect_stdout(io.StringIO()):
            try:return m.live_news_gate('offline-fixture',Path(t))
            finally:self.assertTrue((Path(t)/'news-live.json').exists())
    def test_success(self):
        for s in m.NEWS_SOURCES:
            for edition in m.NEWS_MARKETS:self.assertEqual(self.check(good(s,edition)),(s,edition))
    def test_feed_only_and_empty_not_qualified(self):
        for field in ['feed_count','search_count']:
            for bad in [None,0,-1,41,'3',True,[],{}]:
                r=good();r['attempts'][0][field]=bad
                with self.subTest(field=field,bad=bad),self.assertRaises(RuntimeError):self.check(r)
    def test_stage_confirmation_required(self):
        for stages in [None,[],{}, {'feed':{'status':'ok','count':7}}, {'feed':{'status':'ok','count':7},'search':{'status':'not_tested','count':4}}, {'feed':{'status':'ok','count':True},'search':{'status':'ok','count':4}}]:
            r=good();r['attempts'][0]['stages']=stages
            with self.assertRaises(RuntimeError):self.check(r)
    def test_unsafe_and_mismatched_sources(self):
        for r in [None,{},[],good('http://127.0.0.1'),good(market='any'),dict(good(),provider='reddit'),dict(good(),status='unavailable'),dict(good(),source='google')]:
            with self.assertRaises(RuntimeError):self.check(r)
    def test_execution_failure_report_no_exception_body(self):
        with tempfile.TemporaryDirectory()as t,patch.object(m,'run',side_effect=['',RuntimeError('secret exception')]):
            with self.assertRaises(RuntimeError):m.live_news_gate('offline-fixture',Path(t))
            self.assertNotIn('secret exception',(Path(t)/'news-live.json').read_text())
    def test_terminal_report_bounded(self):
        r={'status':'unavailable','attempts':[{'source':'google','stages':{'feed':{'status':'unavailable','http_status':418,'reason':'http_refused','body':'secret','url':'secret'}}}]}
        with contextlib.redirect_stdout(io.StringIO()) as output,tempfile.TemporaryDirectory()as t,patch.object(m,'run',side_effect=['',json.dumps(r)]):
            with self.assertRaises(RuntimeError):m.live_news_gate('offline-fixture',Path(t))
        self.assertNotIn('secret',output.getvalue());self.assertIn('418',output.getvalue())
    def test_environment_and_private_settings_preserved(self):
        old={'Config':{'Env':['KEEP=private','FOURGET_DEFAULT_SCRAPER_NEWS=reddit','FOURGET_NEWS_RSS_PRIMARY=google']}}
        m.set_news_primary(old,'bing','pt-BR');self.assertIn('KEEP=private',old['Config']['Env']);self.assertIn('FOURGET_DEFAULT_SCRAPER_NEWS=newswire',old['Config']['Env'])
        self.assertEqual(sum(x.startswith('FOURGET_NEWS_RSS_PRIMARY=')for x in old['Config']['Env']),1)
        before=copy.deepcopy(old)
        with self.assertRaises(RuntimeError):m.set_news_primary(old,'https://evil.example','xx')
        self.assertEqual(before,old)
    def test_replacement_verified(self):
        with patch.object(m,'run',return_value='newswire|bing|pt-BR'):m.verify_news_config('fixture','bing','pt-BR')
        with patch.object(m,'run',return_value='reddit|bing|pt-BR'),self.assertRaises(RuntimeError):m.verify_news_config('fixture','bing','pt-BR')
    def test_runtime_ids_match(self):
        r=json.loads(subprocess.check_output(['php','-r','require "lib/news_sources.php"; echo json_encode([array_keys(news_sources::ORIGINS),array_keys(news_sources::MARKETS)]);'],cwd=ROOT,text=True))
        self.assertEqual(r,[list(m.NEWS_SOURCES),list(m.NEWS_MARKETS)])
    def test_gate_order_and_no_skip(self):
        s=(ROOT/'scripts/deploy-ionos.py').read_text();body=s[s.index('def main():'):]
        self.assertLess(body.index('offline_audit('),body.index('live_news_gate('));self.assertLess(body.index('live_news_gate('),body.index('stopped = True'))
        self.assertLess(body.index('live_binternet_gate('),body.index('stopped = True'));self.assertLess(body.index('verify_news_config('),body.index('committed = True'))
        self.assertNotIn('live_redlib_gate(',body)
if __name__=='__main__':unittest.main(verbosity=2)
