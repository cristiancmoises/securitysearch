#!/usr/bin/env python3
"""Actual local HTTP checks for cached/placeholder favicons; no external network."""
from pathlib import Path
import http.client, os, shutil, socket, subprocess, time
ROOT=Path(__file__).resolve().parents[1]
HOST='fixture-cache.invalid'
ICON=ROOT/'icons'/f'{HOST}.png'

def request(port,path):
    c=http.client.HTTPConnection('127.0.0.1',port,timeout=3);c.request('GET',path,headers={'User-Agent':'SecuritySearch-test'})
    r=c.getresponse();body=r.read();headers={k.lower():v for k,v in r.getheaders()};status=r.status;c.close();return status,headers,body

def free_port():
    s=socket.socket();s.bind(('127.0.0.1',0));p=s.getsockname()[1];s.close();return p

def main():
    ICON.parent.mkdir(exist_ok=True)
    shutil.copyfile(ROOT/'lib/favicon404.png',ICON)
    port=free_port();proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(ROOT)],stdout=subprocess.DEVNULL,stderr=subprocess.PIPE,text=True)
    try:
        for _ in range(40):
            try:
                with socket.create_connection(('127.0.0.1',port),.1):break
            except OSError:time.sleep(.05)
        else:raise RuntimeError('PHP test server did not start')
        st,h,b=request(port,f'/favicon.php?s=https://{HOST}')
        assert st==200 and b.startswith(b'\x89PNG\r\n\x1a\n'),(st,h,len(b))
        assert 'public' in h.get('cache-control','') and 'max-age=86400' in h.get('cache-control',''),h
        assert int(h.get('content-length','-1'))==len(b) and h.get('x-content-type-options')=='nosniff',h
        st,h,b=request(port,'/favicon.php?s=404')
        assert st==404 and b.startswith(b'\x89PNG\r\n\x1a\n'),(st,h,len(b))
        assert 'public' in h.get('cache-control','') and 'max-age=300' in h.get('cache-control',''),h
        assert int(h.get('content-length','-1'))==len(b),h
        print('PASS: cached favicon and placeholder responses use bounded public browser caching.')
    finally:
        proc.terminate()
        try:proc.wait(timeout=2)
        except subprocess.TimeoutExpired:proc.kill();proc.wait()
        ICON.unlink(missing_ok=True)
        if proc.returncode not in (0,-15):
            err=proc.stderr.read() if proc.stderr else ''
            raise RuntimeError('PHP test server failed: '+err[-500:])
if __name__=='__main__':main()
