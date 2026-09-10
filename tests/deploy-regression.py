#!/usr/bin/env python3
"""Offline stateful Docker API transaction simulation; no daemon/VPS access."""
import copy
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import urllib.parse
REAL_RUN=subprocess.run
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('deploy',Path(__file__).parents[1]/'scripts/deploy-ionos.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
OLD_ID='a'*64
old={'Id':OLD_ID,'Name':'/security-search','State':{'Running':True},'Config':{'Image':'old','Env':['EXAMPLE=preserved'],'Volumes':{m.APP+'/icons':{}},'Labels':{'com.docker.compose.project':'old','custom':'preserved'}},
 'HostConfig':{'Binds':None,'NetworkMode':'original-net','RestartPolicy':{'Name':'always','MaximumRetryCount':0},'PortBindings':{'80/tcp':[{'HostIp':'172.17.0.1','HostPort':'5140'}]}},
 'NetworkSettings':{'Networks':{'original-net':{'Aliases':['security-search'],'IPAMConfig':None}}},
 'Mounts':[{'Type':'volume','Name':'existing-anonymous-volume','Destination':m.APP+'/icons','RW':True}]}
built={'Config':{'Entrypoint':['new-entrypoint'],'Cmd':['start'],'WorkingDir':m.APP,'Healthcheck':{'Test':['CMD','true']}}}
with patch.object(m,'api',return_value=built):
 candidate=m.create_payload(old,'new',True);live=m.create_payload(old,'new')
 assert candidate['HostConfig']['PortBindings']=={} and candidate['HostConfig']['RestartPolicy']=={'Name':'no'}
 assert candidate['NetworkingConfig']['EndpointsConfig']=={'original-net':{}}
 assert 'existing-anonymous-volume:'+m.APP+'/icons' in live['HostConfig']['Binds']
 assert live['HostConfig']['PortBindings']==old['HostConfig']['PortBindings']
 assert live['Image']=='new' and live['Env']==['EXAMPLE=preserved'] and old['HostConfig']['Binds'] is None
 assert live['Entrypoint']==['new-entrypoint']
print('PASS: candidate isolation, original port, named anonymous volume, environment and new startup.')

class Engine:
 def __init__(self,mode,source=old):
  self.mode=mode;self.containers={OLD_ID:copy.deepcopy(source)};self.calls=[];self.lost=False
 def find(self,name):
  found=next((c for c in self.containers.values() if c['Id']==name or c['Name']=='/'+name),None)
  if found is None:raise m.DockerAPIError('GET',404)
  return found
 def api(self,method,url,data=None):
  self.calls.append((method,url,copy.deepcopy(data)))
  path=urllib.parse.unquote(urllib.parse.urlsplit(url).path);query=urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)
  if path.startswith('/images/'):return built
  if path=='/containers/create':
   name=query['name'][0];cid='candidate' if '-candidate-' in name else 'replacement'
   assert all(c['Name']!='/'+name for c in self.containers.values()),'Docker name collision'
   self.containers[cid]={'Id':cid,'Name':'/'+name,'Config':copy.deepcopy(data),'HostConfig':copy.deepcopy(data['HostConfig']),'State':{'Running':False}}
   if self.mode=='lost_'+cid+'_create' and not self.lost:
    self.lost=True;raise RuntimeError('Simulated response loss after Docker created container')
   return {'Id':cid}
  parts=path.split('/');cid=parts[2];item=self.find(cid);action=parts[3] if len(parts)>3 else ''
  if method=='GET':return copy.deepcopy(item)
  if method=='DELETE':
   if self.mode=='cleanup_failure' and item['Id']=='replacement':raise RuntimeError('Simulated cleanup failure')
   del self.containers[item['Id']];return None
  if action=='start':item['State']['Running']=True
  elif action=='stop':item['State']['Running']=False
  elif action=='update':item['HostConfig'].update(copy.deepcopy(data))
  elif action=='rename':
   name=query['name'][0]
   if any(c['Name']=='/'+name and c['Id']!=item['Id'] for c in self.containers.values()):raise m.DockerAPIError('POST',409)
   item['Name']='/'+name
   if self.mode=='lost_rename' and '-rollback-' in name and not self.lost:
    self.lost=True;raise RuntimeError('Simulated response loss after Docker renamed old container')
  return None
 def healthy(self,cid):
  if cid=='replacement' and self.mode in ('readiness_failure','cleanup_failure'):raise RuntimeError('Simulated failed replacement readiness')

for mode in ['success','readiness_failure','cleanup_failure','lost_candidate_create','lost_rename','lost_replacement_create','offline_audit_failure','live_audit_failure','news_audit_failure']:
 engine=Engine(mode)
 def run(*args,capture=False):
  return json.dumps({'VERSION':19,'DEFAULT_THEME':'Lain','SERVER_NAME':'original','API_ENABLED':True}) if 'php' in args else ''
 with tempfile.TemporaryDirectory() as temp,patch.object(m,'BACKUP_ROOT',Path(temp)/'backups'),patch.object(m,'LOCK_PATH',str(Path(temp)/'lock')),patch.object(m,'api',side_effect=engine.api),patch.object(m,'run',side_effect=run),patch.object(m,'healthy',side_effect=engine.healthy),patch.object(m,'offline_audit',side_effect=RuntimeError('Audit failed') if mode=='offline_audit_failure' else None),patch.object(m,'live_redlib_gate',return_value=m.REDLIB_ORIGINS[1],side_effect=RuntimeError('News gate failed') if mode=='news_audit_failure' else None),patch.object(m,'verify_redlib_config'),patch.object(m,'live_binternet_gate',side_effect=RuntimeError('Live gate failed') if mode=='live_audit_failure' else None),patch.object(m.os,'geteuid',return_value=0),patch.object(m.shutil,'which',return_value='/fixture/program'),patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess([],1)):
  failed=False
  try:m.main()
  except RuntimeError:failed=True
  assert failed==(mode!='success')
  previous=engine.containers[OLD_ID]
  if mode in ('offline_audit_failure','live_audit_failure','news_audit_failure'):
   assert previous['State']['Running'] and previous['Name']=='/security-search'
   assert not any(method=='POST' and '/'+OLD_ID+'/' in url for method,url,data in engine.calls),'Audit failure touched production'
  assert 'candidate' not in engine.containers,'Candidate leaked after response loss'
  if mode=='success':
   assert not previous['State']['Running'] and previous['HostConfig']['RestartPolicy']=={'Name':'no'}
   assert engine.find('security-search')['Id']=='replacement'
   assert engine.containers['replacement']['HostConfig']['RestartPolicy']['Name']=='always'
   migrated_env=engine.containers['replacement']['Config']['Env']
   assert 'FOURGET_DEFAULT_THEME=Black' in migrated_env and 'FOURGET_SERVER_NAME=original' in migrated_env
   assert 'FOURGET_DEFAULT_THEME=Lain' not in migrated_env
   assert 'FOURGET_REDLIB_PRIMARY='+m.REDLIB_ORIGINS[1] in migrated_env
  else:
   assert previous['State']['Running'],'Previous container was not restarted'
   assert previous['HostConfig']['RestartPolicy']==old['HostConfig']['RestartPolicy'],'Original restart policy not restored'
   if mode!='cleanup_failure':assert previous['Name']=='/security-search'
  if mode not in ('lost_candidate_create','offline_audit_failure','live_audit_failure','news_audit_failure'):
   rollback=next((Path(temp)/'backups').glob('*/rollback.sh'));body=rollback.read_text()
   assert 'flock -n 9' in body and 'docker update --restart=always' in body and 'current_image=' in body
   # Check the actual generated shell syntax without executing Docker commands.
   REAL_RUN(['sh','-n',str(rollback)],check=True)
 print('PASS: deployment transaction '+mode)

for destination in ['/etc','/etc/apache2','/etc/php84/conf.d','/etc/ImageMagick-7',m.APP+'/data/config.php']:
 source=copy.deepcopy(old);source['Mounts'].append({'Type':'bind','Destination':destination,'RW':True})
 engine=Engine('success',source)
 with patch.object(m,'api',side_effect=engine.api),patch.object(m.os,'geteuid',return_value=0),patch.object(m.shutil,'which',return_value='/fixture/program'),tempfile.TemporaryDirectory() as temp,patch.object(m,'LOCK_PATH',str(Path(temp)/'lock')):
  try:m.main();raise AssertionError('Masking mount accepted')
  except RuntimeError:pass
  assert all(method=='GET' for method,path,data in engine.calls),'Preflight mutated Docker'
print('PASS: shared Apache/PHP/ImageMagick and application configuration mounts rejected before mutation.')

for failure in [None,'version','theme','theme_asset','script','csp','image_csp','image_asset','motion_asset','adapters']:
 responses=['20|Tron' if failure=='version' else ('23|Lain' if failure=='theme' else '27|Black'),
  'In Code We Trust. zupt-web.securityops.co '+('/static/themes/Lain.css?v27' if failure=='theme_asset' else '/static/themes/Black.css?v27')+('<script src="x"></script>' if failure=='script' else ''),
  "Content-Security-Policy: script-src 'self'" if failure=='csp' else "Content-Security-Policy: script-src 'none'; connect-src 'none'",
  "script-src 'none'" if failure=='image_csp' else "script-src 'self'; connect-src 'self'",
  'not-ready' if failure=='image_asset' else 'IntersectionObserver createDocumentFragment',
  'missing' if failure=='motion_asset' else 'MutationObserver MAX_PLAYING',
  'missing' if failure=='adapters' else 'ready']
 with patch.object(m,'inspect',return_value={'State':{'Health':{'Status':'healthy'}}}),patch.object(m,'run',side_effect=responses):
  try:m.healthy('candidate');assert failure is None
  except RuntimeError:assert failure is not None
print('PASS: candidate gates validate asset 27, effective Black, homepage no-script CSP, scoped image CSP and enhancer asset.')
