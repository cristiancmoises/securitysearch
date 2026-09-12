"""Run the real proxy controller; only upstream HTTP is a fixed-file double.
With decoder_tripwire=True, annotate the conversion branch and stop before
ImageMagick. With False, require the real installed ImageMagick PHP extension.
Never resolves a remote hostname or opens an outbound provider connection.
"""
import http.client, json, os, shutil, socket, struct, subprocess, tempfile, time, zlib
from pathlib import Path

def chunk(kind, data):
    return struct.pack('!I', len(data)) + kind + data + struct.pack('!I', zlib.crc32(kind + data) & 0xffffffff)

def png(w=120,h=80,color=2,metadata=False):
    channels = {0:1,2:3,6:4}[color]
    raw = b''.join(b'\0'+bytes((x*17+y*13+c*31)%256 for x in range(w) for c in range(channels)) for y in range(h))
    body = b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!2I5B',w,h,8,color,0,0,0))
    if metadata: body += chunk(b'tEXt',b'Comment\0private-fixture')
    return body + chunk(b'IDAT',zlib.compress(raw,9)) + chunk(b'IEND',b'')

class ProxyFixture:
    def __init__(self,source,decoder_tripwire=True):
        self.tmp=tempfile.TemporaryDirectory();self.root=Path(self.tmp.name);self.proc=None;self.log=None
        for d in ('lib','data','fixtures'):(self.root/d).mkdir()
        files=('proxy.php','data/config.php','lib/security_headers_minimal.php','lib/animated_preview.php','lib/image_poster.php','lib/img404.png')
        for name in files:shutil.copy2(source/name,self.root/name)
        if (source/'lib/thumbnail_png.php').exists():shutil.copy2(source/'lib/thumbnail_png.php',self.root/'lib/thumbnail_png.php')
        if decoder_tripwire:
            f=self.root/'proxy.php';s=f.read_text();needle='\t$imagick_resource_limits = ['
            if s.count(needle)!=1:raise RuntimeError('Unknown ImageMagick branch for test annotation')
            s=s.replace(needle,'\tfile_put_contents(__DIR__."/decoder-called", "yes");\n\tthrow new Exception("OFFLINE_DECODER_TRIPWIRE");\n'+needle);f.write_text(s)
        (self.root/'lib/curlproxy.php').write_text('''<?php
class proxy {
 const req_image=1;
 function __construct($cache=true){}
 function get(...$args) {
  file_put_contents(__DIR__."/../fetch-called", "yes");
  $status=json_decode(file_get_contents(__DIR__."/../fixtures/status.json"),true);
  return ["http"=>["code"=>$status],"headers"=>[],"body"=>file_get_contents(__DIR__."/../fixtures/body")];
 }
 function getfilenameheader($headers,$url,$type="jpg"){}
 function do404(){http_response_code(404);header("Content-Type: image/png");echo file_get_contents(__DIR__."/img404.png");exit;}
}
''')
        self.set(png())
        with socket.socket() as s:s.bind(('127.0.0.1',0));self.port=s.getsockname()[1]
        self.log=(self.root/'php.log').open('wb')
        self.proc=subprocess.Popen(['php','-d','display_errors=0','-d','error_reporting=32767','-S',f'127.0.0.1:{self.port}','-t',str(self.root)],stdout=self.log,stderr=self.log)
        for _ in range(100):
            if self.proc.poll() is not None:self.close();raise RuntimeError('PHP fixture exited')
            try:
                with socket.create_connection(('127.0.0.1',self.port),.1):return
            except OSError:time.sleep(.02)
        self.close();raise RuntimeError('PHP fixture failed to start')
    def close(self):
        if self.proc is not None and self.proc.poll() is None:
            self.proc.terminate()
            try:self.proc.wait(timeout=3)
            except subprocess.TimeoutExpired:self.proc.kill();self.proc.wait(timeout=3)
        if self.log:self.log.close()
        self.tmp.cleanup()
    def set(self,body,status=200):
        (self.root/'fixtures/body').write_bytes(body);(self.root/'fixtures/status.json').write_text(json.dumps(status))
        for name in ('decoder-called','fetch-called'):(self.root/name).unlink(missing_ok=True)
    def request(self,mode='thumb',method='GET',extra=''):
        c=http.client.HTTPConnection('127.0.0.1',self.port,timeout=4)
        try:
            c.request(method,'/proxy.php?i=https%3A%2F%2Ffixture.invalid%2Fopaque.png&s='+mode+extra)
            r=c.getresponse();return r.status,dict((k.lower(),v) for k,v in r.getheaders()),r.read()
        finally:c.close()
    @property
    def decoded(self):return (self.root/'decoder-called').exists()
