#!/usr/bin/env python3
"""Native Apache fast-home boundary. No external network or provider requests."""
from pathlib import Path
import gzip,os,shutil,subprocess,time,urllib.error,urllib.request
ROOT=Path(__file__).resolve().parents[1]
if not shutil.which('httpd') or not shutil.which('curl'):
    raise SystemExit('BLOCKED: native Alpine httpd/curl are required for static-home HTTP validation.')

env=os.environ.copy();env['FOURGET_PROTO']='http';env['SECURITYSEARCH_STATIC_HOME']='1';env['SECURITYSEARCH_RENDER_BUNDLE']='1'
proc=subprocess.Popen(['sh','docker/docker-entrypoint.sh','start'],cwd=ROOT,env=env,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)

def curl(*args):
    p=subprocess.run(['curl','-sS','--max-time','3',*args],cwd=ROOT,text=True,capture_output=True,timeout=5)
    return p.returncode,p.stdout,p.stderr
try:
    for _ in range(60):
        if proc.poll() is not None: raise RuntimeError('Apache exited before readiness: '+(proc.stdout.read() if proc.stdout else ''))
        if curl('-o','/dev/null','http://127.0.0.1/')[0]==0: break
        time.sleep(.1)
    else: raise RuntimeError('Apache did not become ready.')

    def response(extra=(),method='GET',path='/'):
        body_path=Path('/tmp/securitysearch-static-body')
        body_path.unlink(missing_ok=True)
        url='http://127.0.0.1'+path
        # curl -X HEAD changes only the request verb; curl still expects a body
        # matching Content-Length and can therefore time out on a correct HEAD
        # response.  --head gives curl HEAD semantics and no-body expectations.
        if method=='HEAD':
            cmd=['-D','-','-o','/dev/null','--head',*extra,url]
        else:
            cmd=['-D','-','-o',str(body_path),'-X',method,*extra,url]
        code,out,err=curl(*cmd)
        if code: raise RuntimeError(err)
        headers={}
        status=0
        for line in out.replace('\r','').splitlines():
            if line.startswith('HTTP/'): status=int(line.split()[1]);headers={}
            elif ':' in line:
                k,v=line.split(':',1);headers.setdefault(k.lower(),[]).append(v.strip())
        body=b'' if method=='HEAD' else (body_path.read_bytes() if body_path.exists() else b'')
        return status,headers,body

    status,h,static=response();assert status==200 and h.get('x-securitysearch-render')==['static-home']
    assert any('public, max-age=60' in x for x in h.get('cache-control',[]));assert not h.get('set-cookie')
    assert any("script-src 'none'" in x and "connect-src 'none'" in x for x in h.get('content-security-policy',[]))
    vary=','.join(h.get('vary',[])).lower();assert 'cookie' in vary and 'authorization' in vary and 'accept-encoding' in vary

    status,h,compressed=response(extra=['-H','Accept-Encoding: gzip'])
    assert status==200 and h.get('x-securitysearch-render')==['static-home']
    assert h.get('content-encoding')==['gzip'] and gzip.decompress(compressed)==static

    status,h,identity=response(extra=['-H','Accept-Encoding: gzip;q=0'])
    assert status==200 and h.get('x-securitysearch-render')==['static-home']
    assert not h.get('content-encoding') and identity==static

    for extra,path in [(['-H','Cookie: theme=Black'],'/'),([], '/?probe=1'),(['-H','Authorization: Bearer fixture'],'/')]:
        status,h,body=response(extra=extra,path=path);assert status==200
        assert h.get('x-securitysearch-render',[None])[0] in ('compiled','dynamic')
        assert any('private' in x and 'no-store' in x for x in h.get('cache-control',[]))
        # A Cookie must bypass the anonymous static artifact.  Do not require
        # byte equality with the anonymous snapshot: cookie-aware rendering is
        # allowed to differ while retaining the selected Black appearance.
        if path=='/' and extra and extra[1].startswith('Cookie:'):
            assert b'data-home-style=\"black\"' in body and b'Security Search' in body
            assert b'<script' not in body.lower()

    status,_,_=response(path='/home-anonymous.generated.fast');assert status==404
    status,_,_=response(path='/home-anonymous.generated.fast.gz');assert status==404
    status,h,body=response(method='HEAD');assert status==200 and h.get('x-securitysearch-render')==['static-home'] and body==b''
    print('PASS: native Apache anonymous root serves precompressed/plain static fast paths; cookie/query/auth requests stay dynamic with equivalent Black HTML.')
finally:
    proc.terminate()
    try: proc.wait(timeout=5)
    except subprocess.TimeoutExpired:
        proc.kill();proc.wait(timeout=2)
    Path('/tmp/securitysearch-static-body').unlink(missing_ok=True)
