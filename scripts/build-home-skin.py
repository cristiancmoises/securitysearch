#!/usr/bin/env python3
"""Compile the bundled homepage skin without a client-side build/runtime dependency.
Requires tinycss2 only for maintainers. Do not run in production or from page requests.
"""
from pathlib import Path
import re,hashlib,json
import tinycss2
R=Path(__file__).resolve().parents[1]
def compact(tokens):
 out=[]
 for token in tokens:
  if token.type in ('whitespace','comment'):
   if not out or out[-1]!=' ':out.append(' ')
  elif token.type=='function':out.append(token.name+'('+compact(token.arguments)+')')
  elif token.type.endswith('block'):
   left,right={'{} block':('{}'),'() block':('()'),'[] block':('[]')}[token.type]
   out.append(left+compact(token.content)+right)
  else:out.append(token.serialize())
 return ''.join(out).strip()
def main():
 src=R/'static/home-skin.source.css';raw=src.read_text()
 css=compact(tinycss2.parse_component_value_list(raw))
 if '</style' in css.lower() or '@import' in css.lower():raise ValueError('Unsafe embedded stylesheet')
 page=R/'template/home.html';text=page.read_text()
 updated,n=re.subn(r'<style data-home-skin>.*?</style>',lambda _: '<style data-home-skin>'+css+'</style>',text,flags=re.S)
 if n!=1:raise ValueError('Expected exactly one homepage skin')
 page.write_text(updated)
 manifest={'schema':1,'source_sha256':hashlib.sha256(src.read_bytes()).hexdigest(),'css_sha256':hashlib.sha256(css.encode()).hexdigest(),'raw_bytes':len(raw.encode()),'compiled_bytes':len(css.encode())}
 (R/'data/home-skin-manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
 print('Homepage skin compiled from bundled CSS; no network work.')
if __name__=='__main__':main()
