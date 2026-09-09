#!/usr/bin/env python3
"""Fast-forward main to four explicit HTTPS repositories using separate tokens.
Tokens are prompted privately and passed only in short-lived child environments.
Existing remote configuration, tags and release objects are not changed.
"""
import argparse
import getpass
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile

REMOTES=[('git.securityops.co','cristiancmoises'),('git.securityops.com.br','cristiancmoises'),('github.com','cristiancmoises'),('codeberg.org','berkeley')]
ASKPASS='''#!/usr/bin/env python3
import os,sys,re
from urllib.parse import urlsplit
prompt=sys.argv[1] if len(sys.argv)>1 else ''
match=re.search(r"https://[^'\\s]+",prompt)
if not match:sys.exit(1)
u=urlsplit(match[0])
if u.scheme!='https' or u.hostname!=os.environ['SS_AUTH_HOST'] or u.port not in (None,443):sys.exit(1)
if u.path not in ('/'+os.environ['SS_AUTH_USER']+'/securitysearch','/'+os.environ['SS_AUTH_USER']+'/securitysearch.git'):sys.exit(1)
if prompt.startswith('Username for '): print(os.environ['SS_AUTH_USER'])
elif prompt.startswith('Password for '): print(os.environ['SS_AUTH_TOKEN'])
else:sys.exit(1)
'''

def clean_environment():
    env={k:v for k,v in os.environ.items() if not k.startswith(('GIT_','SS_AUTH_')) and k not in ('SSH_ASKPASS','SSH_ASKPASS_REQUIRE')}
    env.update(GIT_TERMINAL_PROMPT='0',GIT_CONFIG_NOSYSTEM='1',GIT_CONFIG_GLOBAL=os.devnull,LC_ALL='C')
    return env

class Publisher:
    def __init__(self,repo,askpass):self.repo=repo;self.askpass=askpass
    def git(self,*args,host=None,user=None,token=None):
        env=clean_environment()
        command=['git','-C',str(self.repo),'-c','credential.helper=','-c','http.extraHeader=','-c','http.followRedirects=false','-c','http.sslVerify=true','-c','credential.useHttpPath=true']
        if host is not None:
            env.update(GIT_ASKPASS=str(self.askpass),SS_AUTH_HOST=host,SS_AUTH_USER=user,SS_AUTH_TOKEN=token)
            command+=['-c','credential.username='+user,'-c','core.askPass='+str(self.askpass)]
        command+=list(args)
        result=subprocess.run(command,text=True,capture_output=True,env=env,timeout=180)
        if result.returncode:
            # Never echo Git stderr: proxies/credential helpers may echo secrets.
            raise RuntimeError('Git '+args[0]+' failed'+(' for '+host if host else '')+'. Check repository existence, main access, token scope and branch protection. No force operation was attempted.')
        return result.stdout.strip()
    def preflight(self,commit,credentials):
        for (host,user),token in zip(REMOTES,credentials):
            auth=dict(host=host,user=user,token=token);url='https://'+host+'/'+user+'/securitysearch.git'
            self.git('fetch','--no-tags',url,'refs/heads/main',**auth)
            remote=self.git('rev-parse','FETCH_HEAD')
            try:self.git('merge-base','--is-ancestor',remote,commit)
            except RuntimeError:raise RuntimeError(host+' main contains history missing from this checkout. Reconcile it before publishing; nothing has been pushed.')
            self.git('push','--dry-run',url,commit+':refs/heads/main',**auth)
            print(host+': fast-forward preflight passed.',flush=True)
    def publish(self,commit,credentials):
        failed=[]
        for (host,user),token in zip(REMOTES,credentials):
            auth=dict(host=host,user=user,token=token);url='https://'+host+'/'+user+'/securitysearch.git'
            try:
                self.git('push',url,commit+':refs/heads/main',**auth)
                refs=self.git('ls-remote','--heads',url,'refs/heads/main',**auth)
                if refs.split()!=[commit,'refs/heads/main']:raise RuntimeError('Remote main does not match the requested commit.')
                print(host+': verified '+commit,flush=True)
            except (RuntimeError,subprocess.TimeoutExpired):
                failed.append(host);print(host+': publication is unverified; rerun to reconcile. Other hosts are retained.',file=sys.stderr)
        if failed:raise RuntimeError('Not all remotes were verified: '+', '.join(failed)+'. Cross-host publication is not atomic.')

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('repo',type=Path);a=p.parse_args();repo=a.repo.expanduser().resolve()
    with tempfile.TemporaryDirectory(prefix='securitysearch-askpass-') as temp:
        askpass=Path(temp)/'askpass';askpass.write_text(ASKPASS);askpass.chmod(0o700);pub=Publisher(repo,askpass)
        if pub.git('rev-parse','--show-toplevel')!=str(repo):raise RuntimeError('Pass the checkout root.')
        if pub.git('symbolic-ref','--quiet','--short','HEAD')!='main':raise RuntimeError('Use main. No branches are switched automatically.')
        if pub.git('status','--porcelain'):raise RuntimeError('Checkout is not clean. Commit only the intended changes before publishing.')
        if pub.git('show','HEAD:data/release-version.txt')!='0.9.20':raise RuntimeError('This checkout does not contain release 0.9.20.')
        commit=pub.git('rev-parse','HEAD')
        if not re.fullmatch('[0-9a-f]{40,64}',commit):raise RuntimeError('Invalid commit identifier.')
        print('Publishing main commit '+commit+' to four repositories. Tags/releases are unchanged.')
        if not sys.stdin.isatty():raise RuntimeError('Run interactively in a terminal so token entry stays private.')
        credentials=[]
        for host,user in REMOTES:
            token=getpass.getpass('Token for '+host+' ('+user+'): ')
            if not token or any(c in token for c in '\r\n\x00'):raise RuntimeError('An empty or malformed token was supplied.')
            credentials.append(token)
        pub.preflight(commit,credentials)
        if pub.git('rev-parse','HEAD')!=commit or pub.git('status','--porcelain'):
            raise RuntimeError('The checkout changed during preflight. No publication was started.')
        pub.publish(commit,credentials)
        credentials.clear()
if __name__=='__main__':
    try:main()
    except (Exception,KeyboardInterrupt) as e:
        print('Stopped: '+(str(e) if isinstance(e,RuntimeError) else type(e).__name__),file=sys.stderr);sys.exit(1)
