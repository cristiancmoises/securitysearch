#!/usr/bin/env python3
"""Audit-only behavior with synthetic Docker responses and real private record writes."""
import contextlib
import copy
import io
import json
import os
from pathlib import Path
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch
from audit_fixture import load

m = load('deploy-ionos')

def production():
    return {'Id':'a'*64,'Image':'sha256:'+'b'*64,'Name':'/security-search',
            'Created':'2026-09-01T00:00:00Z','RestartCount':0,
            'State':{'Status':'running','Running':True,'Health':{'Status':'healthy'},'StartedAt':'2026-09-01T00:00:00Z'},
            'Config':{'Env':['SECRET=fixture-never-display'],'Image':'fixture-old'},
            'HostConfig':{'RestartPolicy':{'Name':'always'},'PortBindings':{'80/tcp':[{'HostPort':'5140'}]}},
            'Mounts':[],'NetworkSettings':{'Networks':{'fixture-net':{'Aliases':['security-search']}}}}

class AuditOnly(unittest.TestCase):
    def execute(self, *, audit_error=None, build_error=None, drift=False, context=False, free=4*1024**3, inodes=20000, root='/fixture-docker', initial=None):
        calls=[]; old=production() if initial is None else initial; current=copy.deepcopy(old)
        def run(*args, capture=False):
            calls.append(args)
            if args[:2]==('docker','inspect'):return 'c'*64 if context else old['Id']
            if args[:2]==('docker','info'):return root
            if args[:2]==('docker','build') and build_error:raise build_error
            if args[:2]!=('docker','build'):raise AssertionError(args)
            return ''
        def audit(image, backup):
            calls.append(('fixture-offline-audit', image))
            if drift:current['RestartCount']+=1
            if audit_error:raise audit_error
        with tempfile.TemporaryDirectory() as temp:
            directory=Path(temp);out=io.StringIO()
            with patch.object(m,'BACKUP_ROOT',directory/'backups'),patch.object(m,'run',side_effect=run),patch.object(m,'inspect',side_effect=lambda _:copy.deepcopy(current)),patch.object(m,'offline_audit',side_effect=audit),patch.object(m.shutil,'disk_usage',return_value=SimpleNamespace(free=free)),patch.object(m.os,'statvfs',return_value=SimpleNamespace(f_favail=inodes)),contextlib.redirect_stdout(out):
                try:result=m.audit_only('security-search',old);error=None
                except (RuntimeError,KeyboardInterrupt) as err:error=err;result=None
            reports=list(directory.glob('backups/*/audit-only.json'))
            report=json.loads(reports[0].read_text()) if reports else None
            names=[p.name for p in directory.glob('backups/*/*')]
            if reports:
                self.assertTrue(reports[0].parent.name.startswith('audit-only-'))
                self.assertEqual(reports[0].stat().st_mode&0o777,0o600)
            return calls,report,out.getvalue(),error,names

    def test_success_is_not_deployment_evidence(self):
        calls, report, out, error, names=self.execute()
        self.assertIsNone(error);self.assertEqual(report['status'],'ok')
        for key in ('deployed','published','old_object_cleanup'):self.assertIs(report[key],False)
        self.assertEqual(report['live_probes'],'not_run')
        self.assertIn('AUDIT ONLY COMPLETE — NOT DEPLOYED',out);self.assertNotIn('DEPLOY COMPLETE',out)
        self.assertEqual(set(names),{'audit-only.json','source-directory.txt'})
        self.assertNotIn('fixture-never-display',out+json.dumps(report))
        self.assertEqual([x[1] for x in calls if x[0]=='docker'],['inspect','info','build'])
        self.assertTrue(report['image'].startswith('security-search-audit-only:v0.9.40-'))
    def test_failed_audit_retains_non_publishable_failure(self):
        calls, report, out, error, _=self.execute(audit_error=RuntimeError('audit failed'))
        self.assertIsNotNone(error);self.assertEqual(report['status'],'failed');self.assertNotIn('COMPLETE',out)
    def test_build_failure_does_not_run_audit(self):
        calls,report,out,error,_=self.execute(build_error=RuntimeError('build failed'))
        self.assertEqual(report['status'],'failed');self.assertIsNotNone(error)
        self.assertFalse(any(x[0]=='fixture-offline-audit' for x in calls))
    def test_interrupt_is_not_success(self):
        _,report,out,error,_=self.execute(audit_error=KeyboardInterrupt())
        self.assertIsInstance(error,KeyboardInterrupt);self.assertEqual(report['status'],'failed')
    def test_production_drift_cannot_certify(self):
        _,report,out,error,_=self.execute(drift=True)
        self.assertIsNotNone(error);self.assertEqual(report['status'],'failed');self.assertNotIn('COMPLETE',out)
    def test_docker_context_mismatch_stops_before_build(self):
        calls,report,_,error,_=self.execute(context=True)
        self.assertIsNotNone(error);self.assertIsNone(report);self.assertEqual(len(calls),1)
    def test_storage_capacity_refusal_performs_no_cleanup(self):
        for kw in ({'free':2*1024**3-1},{'inodes':9999}):
            calls,report,_,error,_=self.execute(**kw)
            self.assertIsNotNone(error);self.assertIsNone(report);self.assertEqual(len(calls),2)
    def test_invalid_storage_identity_stops(self):
        for root in ('','relative','/root\nextra','/'+'x'*4096):
            calls,report,_,error,_=self.execute(root=root)
            self.assertIsNotNone(error);self.assertIsNone(report)
    def test_unhealthy_production_is_refused(self):
        initial=production();initial['State']['Health']['Status']='unhealthy'
        calls,report,_,error,_=self.execute(initial=initial)
        self.assertIsNotNone(error);self.assertEqual(calls,[])
    def test_main_branches_under_lock_before_private_reads(self):
        with tempfile.TemporaryDirectory() as temp,patch.dict(os.environ,{'SECURITYSEARCH_AUDIT_ONLY':'1','SECURITYSEARCH_CONTAINER':'security-search'}),patch.object(m,'LOCK_PATH',str(Path(temp)/'lock')),patch.object(m.os,'geteuid',return_value=0),patch.object(m.shutil,'which',return_value='/fixture'),patch.object(m,'inspect',return_value=production()),patch.object(m,'audit_only',return_value='audit-only-fixture') as audit,patch.object(m,'run') as run,patch.object(m.fcntl,'flock') as lock:
            self.assertEqual(m.main(),'audit-only-fixture')
            lock.assert_called_once();audit.assert_called_once();run.assert_not_called()
    def test_lock_failure_prevents_audit(self):
        with tempfile.TemporaryDirectory() as temp,patch.dict(os.environ,{'SECURITYSEARCH_AUDIT_ONLY':'1'}),patch.object(m,'LOCK_PATH',str(Path(temp)/'lock')),patch.object(m.os,'geteuid',return_value=0),patch.object(m.shutil,'which',return_value='/fixture'),patch.object(m.fcntl,'flock',side_effect=BlockingIOError()),patch.object(m,'audit_only') as audit:
            with self.assertRaises(BlockingIOError):m.main()
            audit.assert_not_called()

if __name__=='__main__':unittest.main(verbosity=2)
