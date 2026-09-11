#!/usr/bin/env python3
"""Anonymous homepage fast-path contracts. No external network or provider request."""
from pathlib import Path
import gzip,os,re,subprocess,unittest
ROOT=Path(__file__).resolve().parents[1]

def run(*args,env=None):
    e=os.environ.copy();e.update(env or {})
    return subprocess.run(args,cwd=ROOT,text=True,capture_output=True,env=e,timeout=30)

class StaticHome(unittest.TestCase):
    def test_snapshot_matches_anonymous_dynamic_render(self):
        p=run('php','lib/build_view_resources.php','--build');self.assertEqual(p.returncode,0,p.stderr)
        p=run('php','lib/build_home_snapshot.php','--build',env={'SECURITYSEARCH_RENDER_BUNDLE':'1'});self.assertEqual(p.returncode,0,p.stderr)
        snapshot=(ROOT/'home-anonymous.generated.fast').read_text()
        code='$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["SCRIPT_NAME"]="/index.php";$_COOKIE=[];include "index.php";'
        p=run('php','-r',code,env={'SECURITYSEARCH_RENDER_BUNDLE':'1'});self.assertEqual(p.returncode,0,p.stderr)
        self.assertEqual(snapshot,p.stdout)
        gz=ROOT/'home-anonymous.generated.fast.gz'
        self.assertTrue(gz.is_file())
        self.assertEqual(gzip.decompress(gz.read_bytes()).decode(),snapshot)
        self.assertLess(gz.stat().st_size,(ROOT/'home-anonymous.generated.fast').stat().st_size)
        self.assertIn('data-home-style="black"',snapshot);self.assertIn('In Code We Trust.',snapshot)
        self.assertNotIn('<script',snapshot.lower());self.assertNotRegex(snapshot,r'\{%[a-z0-9_-]+%\}')
    def test_builder_atomic_and_rejects_link(self):
        target=ROOT/'home-anonymous.generated.fast';gzip_target=ROOT/'home-anonymous.generated.fast.gz';target.unlink(missing_ok=True);gzip_target.unlink(missing_ok=True);target.symlink_to('/tmp/nope')
        try:
            p=run('php','lib/build_home_snapshot.php','--build');self.assertNotEqual(p.returncode,0);self.assertIn('linked',p.stderr.lower())
        finally: target.unlink(missing_ok=True)
        p=run('php','lib/build_home_snapshot.php','--build');self.assertEqual(p.returncode,0,p.stderr)
        self.assertEqual(target.stat().st_mode&0o022,0);self.assertEqual(gzip_target.stat().st_mode&0o022,0)
    def test_apache_eligibility_is_strict(self):
        for rel in ['docker/apache/http/httpd.conf','docker/apache/https/httpd.conf']:
            text=(ROOT/rel).read_text()
            for marker in ['SECURITYSEARCH_STATIC_HOME','REQUEST_METHOD','QUERY_STRING','HTTP:Cookie','HTTP:Authorization','home-anonymous.generated.fast -f','home-anonymous.generated.fast.gz -f','HTTP:Accept-Encoding']:
                self.assertIn(marker,text,rel)
            self.assertIn('RewriteRule ^$ home-anonymous.generated.fast.gz [END,E=no-gzip:1]',text);self.assertIn('RewriteRule ^$ home-anonymous.generated.fast [END]',text)
            self.assertIn('THE_REQUEST',text);self.assertIn('R=404,END',text)
            self.assertNotRegex(text,re.compile(r'user.?agent|benchmark',re.I))
    def test_static_response_policy(self):
        text=(ROOT/'docker/apache/fast-home.conf').read_text()
        self.assertIn('public, max-age=60, stale-while-revalidate=30',text)
        for marker in ['Vary "Accept-Encoding"','Vary "Cookie"','Vary "Authorization"','X-SecuritySearch-Render "static-home"',"script-src 'none'","connect-src 'none'",'Referrer-Policy "no-referrer"']:
            self.assertIn(marker,text)
    def test_runtime_artifact_not_source_or_docker_input(self):
        for rel in ['.gitignore','.dockerignore']:
            text=(ROOT/rel).read_text();self.assertIn('home-anonymous.generated.fast',text);self.assertIn('home-anonymous.generated.fast.gz',text)
    def test_entrypoint_fails_open_to_dynamic(self):
        text=(ROOT/'docker/docker-entrypoint.sh').read_text()
        self.assertIn('build_home_snapshot.php --build',text)
        self.assertIn('export SECURITYSEARCH_STATIC_HOME=1',text)
        self.assertIn('export SECURITYSEARCH_STATIC_HOME=0',text)
        self.assertIn('rm -f ./home-anonymous.generated.fast ./home-anonymous.generated.fast.gz',text)
    def test_rank_refresh_rebuilds_snapshot(self):
        text=(ROOT/'scripts/install-rank-timer.py').read_text()
        self.assertIn('rank-refresh v2',text);self.assertIn('ExecStartPost=',text);self.assertIn('build_home_snapshot.php --build',text)
        self.assertIn('rank-refresh v1',text) # recognized exact migration only

if __name__=='__main__':unittest.main(verbosity=2)
