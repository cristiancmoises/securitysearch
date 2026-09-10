#!/usr/bin/env python3
"""Rebuild homepage-only CSS (developer tool; needs tinycss2, cssselect2, lxml and PHP).
Keeps stateful selectors conservatively; review browser parity after structural edits.
No frontend runtime dependency or network connection is added.
"""
from pathlib import Path
import re,json,hashlib,subprocess
import tinycss2, cssselect2
from lxml import html
R=Path(__file__).resolve().parents[1]
docs=[]
for name in ['Black','Custom']:
 text=subprocess.check_output(['php','-r',f'$_COOKIE["theme"]="{name}"; $_SERVER["SCRIPT_NAME"]="index.php"; require "index.php";'],cwd=R,text=True)
 docs.append(cssselect2.ElementWrapper.from_html_root(html.fromstring(text)))
nodes=[n for d in docs for n in d.iter_subtree()]
def match(sel):
 sel=re.sub(r'::[a-zA-Z-]+(?:\([^)]*\))?','',sel)
 sel=re.sub(r':(?:hover|focus-visible|focus-within|focus|active|visited|checked|disabled|enabled|target|indeterminate)\b','',sel)
 sel=sel.replace('[open]','')
 try:return any(s.test(n) for s in cssselect2.compile_selector_list(sel) for n in nodes)
 except cssselect2.SelectorError: return True # conservative for unknown selectors

def squash(tokens):
 out=''
 for t in tokens:
  if t.type=='comment':continue
  if t.type=='whitespace':out+=' ';continue
  if t.type.endswith('block'):out+= {'{} block':('{','}'),'() block':('(',')'),'[] block':('[',']')}[t.type][0]+squash(t.content)+{'{} block':('{','}'),'() block':('(',')'),'[] block':('[',']')}[t.type][1]
  elif t.type=='function':out+=t.name+'('+squash(t.arguments)+')'
  else:out+=t.serialize()
 return out.strip()
def emit(rules, filter_rules):
 out=[]
 for rule in rules:
  if rule.type=='qualified-rule':
   sel=tinycss2.serialize(rule.prelude).strip()
   if not filter_rules or match(sel):out.append(squash(rule.prelude)+'{'+squash(rule.content)+'}')
  elif rule.type=='at-rule':
   if rule.content is None:raise ValueError('External CSS imports not permitted')
   if rule.lower_at_keyword in ('media','supports','layer','container'):
    nested=emit(tinycss2.parse_rule_list(rule.content,skip_whitespace=True,skip_comments=True),filter_rules)
   else:nested=squash(rule.content)
   if nested:out.append('@'+rule.at_keyword+' '+squash(rule.prelude)+'{'+nested+'}')
 return '\n'.join(out)
manifest={'schema':1,'generator':'scripts/build-home-css.py','inputs':{},'outputs':{},'note':'Complete homepage states; not only above-the-fold. Results keep full shared CSS.'}
for src,dest,filter_rules in [('static/style.css','static/home-base.css',True),('static/experience.css','static/home-controls.css',True),('static/themes/Black.css','static/home-black.css',False)]:
 raw=(R/src).read_bytes();css=emit(tinycss2.parse_stylesheet(raw.decode(),skip_comments=True,skip_whitespace=True),filter_rules)+'\n'
 assert '</style' not in css.lower() and '@import' not in css.lower()
 (R/dest).write_text(css);manifest['inputs'][src]=hashlib.sha256(raw).hexdigest();manifest['outputs'][dest]=hashlib.sha256(css.encode()).hexdigest();print(src,len(raw),'->',dest,len(css.encode()))
(R/'data/home-css-manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
