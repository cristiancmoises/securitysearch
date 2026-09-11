#!/usr/bin/env python3
"""Static source contracts for event-MPM/FPM and explicit prefork fallback."""
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]

def need(cond,msg):
    if not cond: raise AssertionError(msg)

def one(pattern,text,msg):need(len(re.findall(pattern,text,re.M))==1,msg)

def main():
    docker=(ROOT/'Dockerfile').read_text()
    entry=(ROOT/'docker/docker-entrypoint.sh').read_text()
    fpm=(ROOT/'docker/php-fpm/php-fpm.conf').read_text()
    pool=(ROOT/'docker/php-fpm/securitysearch.conf').read_text()
    apache=(ROOT/'docker/apache/fpm.conf').read_text()
    deploy=(ROOT/'scripts/deploy-ionos.py').read_text()
    for package in ('apache2-proxy','php84-fpm','php84-apache2'): need(package in docker,'missing '+package)
    need('http://127.0.0.1/settings' in docker,'healthcheck does not exercise PHP-backed route')
    need('listen = 127.0.0.1:9000' in pool,'FPM not loopback-only')
    need('listen.allowed_clients = 127.0.0.1' in pool,'FPM allowed clients widened')
    need('pm.max_children = 16' in pool,'PHP concurrency ceiling changed')
    need('clear_env = no' in pool,'runtime build flags would be hidden from FPM')
    need('security.limit_extensions = .php' in pool,'FPM extension restriction missing')
    need('pid = /run/securitysearch-php-fpm.pid' in fpm,'fixed FPM pid missing')
    for module in ('mod_proxy.so','mod_proxy_fcgi.so'): need(module in apache,'missing Apache '+module)
    need('proxy:fcgi://127.0.0.1:9000' in apache,'FPM handler is not loopback')
    need('${SECURITYSEARCH_PHP_RUNTIME:-fpm}' in entry,'FPM is not the documented default')
    need('fpm)' in entry and 'prefork)' in entry,'explicit runtime choices missing')
    need('php-fpm84 -t' in entry and 'httpd -t' in entry,'native config checks missing')
    need('rm -f /etc/apache2/conf.d/php84-module.conf' in entry,'mod_php not removed in FPM mode')
    need('mpm_event_module' in entry,'event MPM not selected')
    need("env['SECURITYSEARCH_PHP_RUNTIME'] = 'fpm'" in deploy,'deployer does not pin candidate FPM')
    need('mpm_event_module' in deploy and 'php_module' in deploy,'readiness does not verify event/no-mod_php')
    for conf in ('docker/apache/http/httpd.conf','docker/apache/https/httpd.conf'):
        text=(ROOT/conf).read_text();need('mpm_prefork_module' in text and 'mpm_event_module' in text,conf+' lacks fallback/event blocks')
        need('MaxRequestWorkers      200' in text,conf+' event capacity missing')
    print('PASS: event-MPM/FPM source contracts and explicit prefork fallback.')
if __name__=='__main__':main()
