#!/usr/bin/env python3
"""Syntax gate for all PHP, JS, Python and fish sources; no upstream traffic."""
import ast
from pathlib import Path
import shutil
import subprocess
import sys
ROOT=Path(__file__).resolve().parents[1]
def main():
    for required in ('php','node','fish'):
        if not shutil.which(required):
            raise RuntimeError('Required audit interpreter missing: '+required)
    count=0
    for path in ROOT.rglob('*'):
        if not path.is_file() or '.git' in path.parts: continue
        command=None
        if path.suffix=='.php': command=['php','-l',str(path)]
        elif path.suffix in ('.js','.cjs'): command=['node','--check',str(path)]
        elif path.suffix=='.fish': command=['fish','--no-execute',str(path)]
        elif path.suffix=='.py': ast.parse(path.read_text(),filename=str(path));count+=1
        if command:
            result=subprocess.run(command,capture_output=True,text=True,timeout=30)
            if result.returncode: raise RuntimeError('Syntax failed: '+str(path.relative_to(ROOT))+'\n'+result.stderr)
            count+=1
    print('PASS: syntax for '+str(count)+' PHP/JavaScript/Python/fish files.')
if __name__=='__main__':
    try: main()
    except Exception as e: print(str(e),file=sys.stderr);sys.exit(1)
