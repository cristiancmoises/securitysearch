#!/usr/bin/env python3
"""Native production-image prerequisites for PHP-FPM/event MPM."""
from pathlib import Path
import os, shutil, subprocess, tempfile
ROOT=Path(__file__).resolve().parents[1]

def main():
    binary=shutil.which('php-fpm84')
    if not binary: raise SystemExit('php-fpm84 is required in the production image.')
    for path in ('/usr/lib/apache2/mod_mpm_event.so','/usr/lib/apache2/mod_proxy.so','/usr/lib/apache2/mod_proxy_fcgi.so'):
        if not Path(path).is_file(): raise SystemExit('Missing Apache runtime module: '+path)
    with tempfile.TemporaryDirectory() as td:
        cfg=Path(td)/'php-fpm.conf'
        cfg.write_text('[global]\npid = '+td+'/fpm.pid\nerror_log = /proc/self/fd/2\ndaemonize = yes\ninclude='+str(ROOT/'docker/php-fpm/securitysearch.conf')+'\n')
        p=subprocess.run([binary,'-t','-y',str(cfg)],stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)
        if p.returncode: raise SystemExit('PHP-FPM configuration test failed without starting a service.')
    print('PASS: native PHP-FPM binary/config and Apache event/proxy_fcgi modules are present.')
if __name__=='__main__':main()
