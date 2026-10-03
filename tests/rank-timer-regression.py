#!/usr/bin/env python3
"""Filesystem/systemd-boundary fixtures only; never operates on host units."""
from pathlib import Path
import gzip, importlib.util, json, os, pwd, shutil, subprocess, tempfile, unittest
s=importlib.util.spec_from_file_location('timer',Path(__file__).resolve().parents[1]/'scripts/install-rank-timer.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class Tests(unittest.TestCase):
 def test_new_and_repeat(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);calls=[]
   def run(args,**kw):calls.append(args);return subprocess.CompletedProcess(args,0)
   self.assertEqual(m.install(root,'/usr/bin/docker',run),0)
   before={p.name:p.read_bytes() for p in root.iterdir()}
   m.install(root,'/usr/bin/docker',run)
   self.assertEqual(before,{p.name:p.read_bytes() for p in root.iterdir()})
   service=before[m.UNIT+'.service'].decode()
   self.assertIn('--user apache',service);self.assertIn('ExecStartPost=',service);self.assertIn('build_home_snapshot.php --build',service)
   self.assertEqual(calls[1],['systemctl','enable','--now',m.UNIT+'.timer'])
 def test_exact_v1_upgrades(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);calls=[]
   for name,text in m._v1_unit_files('/usr/bin/docker').items():(root/name).write_text(text)
   def run(args,**kw):calls.append(args);return subprocess.CompletedProcess(args,0)
   self.assertEqual(m.install(root,'/usr/bin/docker',run),0)
   for name,text in m.unit_files('/usr/bin/docker').items():self.assertEqual((root/name).read_text(),text)
   self.assertEqual(calls[0],['systemctl','daemon-reload'])
 def test_modified_v1_is_preserved(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);files=m._v1_unit_files('/usr/bin/docker')
   (root/(m.UNIT+'.service')).write_text(files[m.UNIT+'.service']+'# local edit\n')
   (root/(m.UNIT+'.timer')).write_text(files[m.UNIT+'.timer'])
   with self.assertRaises(RuntimeError):m.install(root,'/usr/bin/docker',lambda *a,**k:self.fail('No systemd call'))
   self.assertTrue((root/(m.UNIT+'.service')).read_text().endswith('# local edit\n'))
 def test_conflict_no_writes(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);(root/(m.UNIT+'.timer')).write_text('operator managed')
   with self.assertRaises(RuntimeError):m.install(root,'/usr/bin/docker',lambda *a,**k:self.fail('No systemd call'))
   self.assertEqual(len(list(root.iterdir())),1)
 def test_symlink(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);(root/(m.UNIT+'.service')).symlink_to('missing')
   with self.assertRaises(RuntimeError):m.install(root,'/usr/bin/docker',lambda *a,**k:None)
 def test_refresh_failure_keeps_timer(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t)
   def run(args,**kw):return subprocess.CompletedProcess(args,1 if args[1]=='start' else 0)
   self.assertEqual(m.install(root,'/usr/bin/docker',run),1)
   self.assertTrue((root/(m.UNIT+'.timer')).is_file())

class Startup(unittest.TestCase):
 """Run the real startup sequence and cache publisher with offline cURL only."""
 def setUp(self):
  self.php=shutil.which('php')
  if not self.php or os.geteuid()!=0:
   raise RuntimeError('Native PHP and root are required for apache cache-ownership validation.')
  self.apache=pwd.getpwnam('apache').pw_uid
  self.workspace=tempfile.TemporaryDirectory(prefix='securitysearch-rank-startup-')
  self.addCleanup(self.workspace.cleanup)
  self.root=Path(self.workspace.name);self.root.chmod(0o755)
  self.app=self.root/'app';self.app.mkdir()
  source=Path(__file__).resolve().parents[1]
  for directory in ('lib','template','data','static'):(self.app/directory).mkdir()
  for path in (source/'lib').glob('*.php'):shutil.copy2(path,self.app/'lib'/path.name)
  for path in (source/'template').glob('*.html'):shutil.copy2(path,self.app/'template'/path.name)
  shutil.copy2(source/'data/config.php',self.app/'data/config.php')
  for name in ('home-base.css','home-controls.css','home-black.css'):
   shutil.copy2(source/'static'/name,self.app/'static'/name)
  self.tmp=self.root/'tmp';self.tmp.mkdir();self.tmp.chmod(0o1777)
  self.bin=self.root/'bin';self.bin.mkdir()
  # Only unrelated host setup/build commands are replaced. Tranco's parser,
  # atomic cache publication, the apache identity and homepage builder are real.
  for command in ('mkdir','cp','rm'):
   path=self.bin/command;path.write_text('#!/bin/sh\nexit 0\n');path.chmod(0o755)
  path=self.bin/'php';path.write_text('''#!/bin/sh
case "$1" in
 lib/tranco.php|./lib/build_home_snapshot.php)
  exec "$FIXTURE_PHP" -d sys_temp_dir="$FIXTURE_TMP" -d disable_functions=curl_init,curl_setopt_array,curl_exec,curl_getinfo,curl_close -d auto_prepend_file="$FIXTURE_CURL" "$@" ;;
 *) exit 0 ;;
esac
''');path.chmod(0o755)
  self.curl=self.root/'curl-fixture.php';self.curl.write_text('''<?php
function curl_init($url) {
 if ($url!=='https://tranco-list.eu/api/ranks/domain/securityops.co') throw new RuntimeException('Unexpected metadata endpoint.');
 return new stdClass();
}
function curl_setopt_array($handle,$options) {
 if ($options[CURLOPT_FOLLOWLOCATION]!==false || $options[CURLOPT_PROTOCOLS]!==CURLPROTO_HTTPS ||
     $options[CURLOPT_PROXY]!=='' || $options[CURLOPT_SSL_VERIFYPEER]!==true || $options[CURLOPT_SSL_VERIFYHOST]!==2 ||
     $options[CURLOPT_CONNECTTIMEOUT_MS]>1500 || $options[CURLOPT_TIMEOUT_MS]>5000)
  throw new RuntimeException('Metadata transport lost its privacy, TLS or timeout bounds.');
 $GLOBALS['fixture_options']=$options;return true;
}
function curl_exec($handle) {
 file_put_contents(getenv('FIXTURE_FETCH_LOG'),"fetch\\n",FILE_APPEND);
 if (getenv('FIXTURE_FAIL')==='1') return false;
 $rows=getenv('FIXTURE_UNRANKED')==='1' ? [] : [['date'=>gmdate('Y-m-d'),'rank'=>(int)getenv('FIXTURE_RANK')]];
 $body=json_encode(['domain'=>'securityops.co','ranks'=>$rows]);
 $write=$GLOBALS['fixture_options'][CURLOPT_WRITEFUNCTION];
 return $write($handle,$body)===strlen($body);
}
function curl_getinfo($handle,$option) { return getenv('FIXTURE_FAIL')==='1' ? 503 : 200; }
function curl_close($handle) {}
''')
  self.log=self.tmp/'fetch.log'
  script=(source/'docker/docker-entrypoint.sh').read_text()
  # Exercise startup through the homepage stage, before Apache/FPM launch.
  script=script.split('SECURITYSEARCH_PHP_RUNTIME=',1)[0]
  script=script.replace("FOURGET_SRC='/var/www/html/4get'",'FOURGET_SRC='+str(self.app))
  self.entrypoint=self.root/'entrypoint.sh';self.entrypoint.write_text(script)
  self.env=os.environ.copy();self.env.update({
   'FOURGET_PROTO':'http','PATH':str(self.bin)+os.pathsep+os.environ['PATH'],
   'FIXTURE_PHP':self.php,'FIXTURE_TMP':str(self.tmp),'FIXTURE_CURL':str(self.curl),
   'FIXTURE_FETCH_LOG':str(self.log),'FIXTURE_RANK':'12345','FIXTURE_FAIL':'0','FIXTURE_UNRANKED':'0',
   'SECURITYSEARCH_STATIC_HOME':'1','SECURITYSEARCH_RANK_REFRESH':'1'})
 def start(self,**environment):
  self.env.update(environment)
  result=subprocess.run(['sh',str(self.entrypoint)],cwd=self.app,env=self.env,text=True,capture_output=True,timeout=15)
  self.assertEqual(result.returncode,0,result.stdout+result.stderr)
  plain=(self.app/'home-anonymous.generated.fast').read_bytes()
  self.assertTrue(b'Security Search' in plain,'Startup must produce a usable homepage.')
  self.assertEqual(gzip.decompress((self.app/'home-anonymous.generated.fast.gz').read_bytes()),plain)
  return plain
 def test_initial_rank_is_in_snapshot_and_daily_apache_refresh_can_replace_cache(self):
  page=self.start()
  self.assertTrue(b'Tranco #12,345' in page,'Startup must fetch metadata before capturing the anonymous homepage.')
  caches=list(self.tmp.glob('securitysearch-tranco-*.json'));self.assertEqual(len(caches),1)
  self.assertEqual(caches[0].stat().st_uid,self.apache,'The timer must be able to replace the startup cache in sticky /tmp.')
  self.env['FIXTURE_RANK']='54321'
  refresh=subprocess.run(['su','-s','/bin/sh','apache','-c','php lib/tranco.php --refresh'],cwd=self.app,env=self.env,text=True,capture_output=True,timeout=10)
  self.assertEqual(refresh.returncode,0,refresh.stdout+refresh.stderr)
  self.assertEqual(json.loads(caches[0].read_text())['rank'],54321)
 def test_failed_initial_fetch_still_builds_unavailable_home(self):
  page=self.start(FIXTURE_FAIL='1')
  self.assertTrue('Tranco · unavailable'.encode() in page,'Failed bootstrap must honestly report unavailable metadata.')
  self.assertEqual(self.log.read_text(),'fetch\n','Startup should attempt one bounded refresh, then retain an unavailable homepage.')
  self.assertFalse(list(self.tmp.glob('securitysearch-tranco-*.json')))
 def test_offline_optout_builds_home_without_fetch(self):
  page=self.start(SECURITYSEARCH_RANK_REFRESH='0')
  self.assertTrue('Tranco · unavailable'.encode() in page,'Offline startup must honestly report unavailable metadata.')
  self.assertFalse(self.log.exists())
 def test_failed_refresh_retains_valid_cached_rank(self):
  refresh=subprocess.run(['su','-s','/bin/sh','apache','-c','php lib/tranco.php --refresh'],cwd=self.app,env=self.env,text=True,capture_output=True,timeout=10)
  self.assertEqual(refresh.returncode,0,refresh.stdout+refresh.stderr)
  cache=next(self.tmp.glob('securitysearch-tranco-*.json'));previous=cache.read_bytes()
  page=self.start(FIXTURE_FAIL='1')
  self.assertTrue(b'Tranco #12,345' in page,'Failed startup refresh must preserve the previously validated rank.')
  self.assertEqual(cache.read_bytes(),previous)
  self.assertEqual(self.log.read_text(),'fetch\nfetch\n')
 def test_unranked_domain_is_reported_without_inventing_rank(self):
  page=self.start(FIXTURE_UNRANKED='1')
  self.assertTrue('Tranco · not in returned lists'.encode() in page,'An empty upstream list must remain distinct from unavailable metadata.')
  cache=next(self.tmp.glob('securitysearch-tranco-*.json'))
  self.assertIsNone(json.loads(cache.read_text())['rank'])

if __name__=='__main__':unittest.main(verbosity=2)
