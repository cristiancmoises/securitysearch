#!/usr/bin/env python3
"""Prepare a private deployment-only theme pack. Never writes into a Git checkout.
Requires Pillow only for preparation; validation/deployment use Python stdlib.
No original or transformed Lain/SecOps image belongs in a source release.
"""
from __future__ import annotations
import argparse, hashlib, io, json, math, os
from pathlib import Path
import shutil, subprocess, sys, tempfile, urllib.request, urllib.error, urllib.parse, warnings
COMMIT='81979bb217f97df1c6acc724ef7d8c9da2789d7c'
SOURCES={
 'Lain':('lain.gifv','fcd2163ef4f77991b0f66af12099bd13e7322b3c',9056643),
 'SecOps':('secops.gif','b5e32642d24b7e31bdba2a4c06aa7aad1d41cb9f',18952924),
 'Tron':('tron.gif','0a26936f72d7ffe53a1668fafd9ca8e7edfb03e5',12261897),
}
NAMES=tuple(n for name in ('Lain','SecOps') for n in (name.lower()+'.webp',name.lower()+'-still.webp',name+'-preview.webp'))
PREFIX='static/operator-themes'
MAX_TOTAL=18*1024*1024

def blob_id(data:bytes)->str:
    return hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()

def verify_original(name:str,data:bytes)->None:
    filename,blob,size=SOURCES[name]
    if len(data)!=size or blob_id(data)!=blob:
        raise RuntimeError('Historical asset bytes do not match the pinned commit: '+filename)

def load_original(repo:Path,name:str)->bytes:
    filename,blob,size=SOURCES[name]
    candidates=(f'HEAD:static/misc/{filename}',f'{COMMIT}:static/misc/{filename}')
    for ref in candidates:
        p=subprocess.run(['git','-C',str(repo),'show',ref],capture_output=True,timeout=30)
        if p.returncode==0:
            try: verify_original(name,p.stdout);return p.stdout
            except RuntimeError: pass
    urls=(f'https://codeberg.org/berkeley/securitysearch/raw/commit/{COMMIT}/static/misc/{filename}',
          f'https://raw.githubusercontent.com/cristiancmoises/securitysearch/{COMMIT}/static/misc/{filename}')
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,*args,**kwargs): return None
    # Proxy credentials from an ambient environment are not forwarded to downloads.
    opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),NoRedirect())
    for url in urls:
        try:
            request=urllib.request.Request(url,headers={'User-Agent':'SecuritySearch-operator-theme-preparer/0.9.22'})
            with opener.open(request,timeout=45) as response:
                data=response.read(size+1)
            verify_original(name,data)
            return data
        except (OSError,RuntimeError) as error:
            print('Pinned asset unavailable from '+urllib.parse.urlsplit(url).hostname+' ('+type(error).__name__+'); trying the next pinned source.',file=sys.stderr)
    raise RuntimeError('Could not recover '+filename+'. Fetch the historical commit in your local repository or retry when its pinned sources are reachable. No pack was installed.')

def convert(data:bytes,name:str,destination:Path)->None:
    try:
        from PIL import Image, ImageOps, features
    except ImportError as error:
        raise RuntimeError('Pillow is required for preparation. On Guix use: guix shell python python-pillow -- python3 scripts/operator_themes.py ...') from error
    if not features.check('webp'): raise RuntimeError('This Pillow build needs animated WebP support.')
    Image.MAX_IMAGE_PIXELS=8_000_000
    with warnings.catch_warnings():
        warnings.simplefilter('error',Image.DecompressionBombWarning)
        with Image.open(io.BytesIO(data)) as im:
            if im.format not in ('GIF','WEBP') or im.width*im.height>8_000_000:
                raise RuntimeError('Unsupported or oversized historical animation: '+name)
            count=getattr(im,'n_frames',1)
            if not 2<=count<=600: raise RuntimeError('Expected a bounded animated raster: '+name)
            stride=max(1,math.ceil(count/90));frames=[];durations=[]
            for i in range(count):
                im.seek(i)
                duration=max(20,min(1000,int(im.info.get('duration',100))))
                if i%stride==0:
                    frame=im.convert('RGB');frame.thumbnail((640,400),Image.Resampling.LANCZOS)
                    frames.append(frame.copy());durations.append(duration)
                else: durations[-1]+=duration
            slug=name.lower()
            frames[0].save(destination/(slug+'.webp'),'WEBP',save_all=True,append_images=frames[1:],duration=durations,loop=0,quality=65,method=4)
            frames[0].save(destination/(slug+'-still.webp'),'WEBP',quality=82,method=4)
            ImageOps.fit(frames[0],(240,135),method=Image.Resampling.LANCZOS).save(destination/(name+'-preview.webp'),'WEBP',quality=76,method=4)
    for filename in (name.lower()+'.webp',name.lower()+'-still.webp',name+'-preview.webp'):
        if (destination/filename).stat().st_size>8*1024*1024: raise RuntimeError('Converted theme exceeds its byte budget.')
    if (destination/(name+'-preview.webp')).stat().st_size>16383: raise RuntimeError('Theme preview exceeds 16 KiB.')

def validate(directory:Path)->dict:
    directory=Path(directory).expanduser().absolute()
    if directory.is_symlink() or not directory.is_dir(): raise RuntimeError('Theme pack must be a real directory, not a symlink.')
    expected=set(NAMES)|{'manifest.json'}
    if {p.name for p in directory.iterdir()}!=expected: raise RuntimeError('Theme pack has missing or unexpected files.')
    for filename in expected:
        p=directory/filename
        if p.is_symlink() or not p.is_file(): raise RuntimeError('Theme pack links/special files are forbidden.')
    p=directory/'manifest.json'
    if p.stat().st_size>8192: raise RuntimeError('Theme manifest too large.')
    manifest=json.loads(p.read_text())
    if manifest.get('schema')!=1 or manifest.get('source_commit')!=COMMIT or manifest.get('deployment_only') is not True:
        raise RuntimeError('Unknown theme pack provenance.')
    if manifest.get('source_blobs')!={name:SOURCES[name][1] for name in ('Lain','SecOps')}:
        raise RuntimeError('Unexpected historical source identifiers.')
    assets=manifest.get('assets')
    if not isinstance(assets,dict) or set(assets)!=set(NAMES): raise RuntimeError('Unexpected theme file manifest.')
    total=0
    for name,row in assets.items():
        path=directory/name;size=path.stat().st_size;total+=size
        limit=16383 if name.endswith('-preview.webp') else 8*1024*1024
        if not isinstance(row,dict) or type(row.get('size')) is not int or not 12<size<=limit or size!=row['size']:
            raise RuntimeError('Theme file size mismatch: '+name)
        raw=path.read_bytes()
        if raw[:4]!=b'RIFF' or raw[8:12]!=b'WEBP' or hashlib.sha256(raw).hexdigest()!=row.get('sha256'):
            raise RuntimeError('Theme file integrity mismatch: '+name)
    if total>MAX_TOTAL: raise RuntimeError('Theme pack exceeds its total byte budget.')
    return manifest

def prepare(repo:Path,out:Path)->None:
    repo=repo.expanduser().resolve();out=out.expanduser().absolute()
    root=subprocess.run(['git','-C',str(repo),'rev-parse','--show-toplevel'],capture_output=True,text=True,check=True).stdout.strip()
    if Path(root).resolve()!=repo: raise RuntimeError('Pass the repository root.')
    if out.is_symlink(): raise RuntimeError('Refusing symlink output.')
    out=out.resolve()
    if out==repo or repo in out.parents: raise RuntimeError('Keep operator assets OUTSIDE the Git checkout.')
    if out.exists():
        validate(out);print('Existing verified operator pack retained: '+str(out));return
    out.parent.mkdir(parents=True,exist_ok=True)
    # This proves the public Tron image is the historical one; no replacement needed.
    load_original(repo,'Tron')
    with tempfile.TemporaryDirectory(prefix='.securitysearch-themes-',dir=out.parent) as temp:
        work=Path(temp);pack=work/'pack';pack.mkdir()
        for name in ('Lain','SecOps'):
            raw=load_original(repo,name);source=work/(name+'.original');source.write_bytes(raw)
            # A bounded child limits pathological decode/encode CPU work.
            subprocess.run([sys.executable,str(Path(__file__).resolve()),'_convert',name,str(source),str(pack)],check=True,timeout=180)
        assets={name:{'sha256':hashlib.sha256((pack/name).read_bytes()).hexdigest(),'size':(pack/name).stat().st_size} for name in NAMES}
        manifest={'schema':1,'deployment_only':True,'source_commit':COMMIT,
                  'source_blobs':{name:SOURCES[name][1] for name in ('Lain','SecOps')},'assets':assets}
        (pack/'manifest.json').write_text(json.dumps(manifest,sort_keys=True,indent=2)+'\n')
        validate(pack)
        if out.exists(): raise RuntimeError('Output appeared during preparation; it was preserved.')
        pack.rename(out)
    print('Private historical theme pack prepared and verified: '+str(out))
    print('Do not commit, attach to source releases, or upload this pack to Codeberg.')

def main():
    if len(sys.argv)==5 and sys.argv[1]=='_convert':
        name=sys.argv[2]
        if name not in ('Lain','SecOps'): raise RuntimeError('Unknown theme.')
        data=Path(sys.argv[3]).read_bytes();verify_original(name,data);convert(data,name,Path(sys.argv[4]));return
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--repo',type=Path);p.add_argument('--output',type=Path);p.add_argument('--validate',type=Path)
    a=p.parse_args()
    if a.validate: validate(a.validate);print('Operator pack hashes verified.');return
    if not a.repo or not a.output: p.error('--repo and --output are required for preparation.')
    prepare(a.repo,a.output)
if __name__=='__main__':
    try: main()
    except (Exception,KeyboardInterrupt) as e:
        print('Stopped: '+(str(e) if isinstance(e,RuntimeError) else type(e).__name__)+'. Existing source and packs were preserved.',file=sys.stderr);sys.exit(1)
