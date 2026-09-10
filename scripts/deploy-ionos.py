#!/usr/bin/env python3
"""Build, stage and replace the existing container; restore it on cutover failure.
Run as root on the Docker host. Uses the local Docker socket, never a remote API.
"""
import copy
import datetime
import fcntl
import http.client
import importlib.util
import json
import os
from pathlib import Path
import signal
import shutil
import re
import socket
import subprocess
import sys
import time
import tempfile
import urllib.parse

ROOT = Path(__file__).resolve().parents[1]
VERSION = '0.9.23'
APP = '/var/www/html/4get'
BACKUP_ROOT = Path('/root/securitysearch-backups')
LOCK_PATH = '/run/lock/securitysearch-update.lock'

class DockerAPIError(RuntimeError):
    def __init__(self, method, status):
        self.status = status
        super().__init__(f'Docker API {method} failed (HTTP {status}).')

class UnixHTTP(http.client.HTTPConnection):
    def connect(self):
        self.sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        self.sock.settimeout(self.timeout)
        self.sock.connect('/var/run/docker.sock')

def api(method, path, data=None):
    conn = UnixHTTP('localhost', timeout=90)
    try:
        body = json.dumps(data).encode() if data is not None else None
        conn.request(method, path, body, {'Content-Type': 'application/json'})
        response = conn.getresponse()
        raw = response.read()
        if response.status >= 400:
            # Engine errors may contain environment values or private paths.
            raise DockerAPIError(method, response.status)
        return json.loads(raw) if raw else None
    finally:
        conn.close()

def run(*args, capture=False):
    return subprocess.run(args, check=True, text=True,
                          stdout=subprocess.PIPE if capture else None,
                          stderr=subprocess.PIPE if capture else None).stdout

def inspect(name):
    return api('GET', '/containers/'+urllib.parse.quote(name, safe='')+'/json')

def find_container(name):
    try:
        return inspect(name)
    except DockerAPIError as error:
        if error.status == 404:
            return None
        raise

def owned_container(name, image):
    found = find_container(name)
    if found is None:
        return None
    config = found.get('Config', {})
    if config.get('Image') != image or config.get('Labels', {}).get('co.securityops.release') != VERSION:
        raise RuntimeError('A staging name belongs to another container; cleanup stopped.')
    return found['Id']

def create_payload(old, image, candidate=False):
    config = copy.deepcopy(old['Config'])
    config['Image'] = image
    # The new image owns startup and health behavior. Preserve service settings.
    built = api('GET', '/images/'+urllib.parse.quote(image, safe='')+'/json')['Config']
    for key in ('Entrypoint','Cmd','WorkingDir','Healthcheck'):
        if key in built:
            config[key] = built[key]
        else:
            config.pop(key, None)
    config['Labels'] = {k:v for k,v in (config.get('Labels') or {}).items()
                        if not k.startswith('com.docker.compose.')}
    config['Labels']['co.securityops.release'] = VERSION
    host = copy.deepcopy(old['HostConfig'])
    host['AutoRemove'] = False
    host['ContainerIDFile'] = ''
    # Only retained read-only private data mounts are added here.
    host.setdefault('Binds', [])
    if host['Binds'] is None:
        host['Binds'] = []
    # Image-declared anonymous volumes appear only in inspect Mounts. Bind
    # their actual names so Docker does not silently allocate empty replacements.
    explicit = {m.get('Target') for m in host.get('Mounts', [])}
    for bind in host['Binds']:
        parts = bind.split(':')
        if len(parts) >= 2:
            explicit.add(parts[1])
    for mount in old.get('Mounts', []):
        dest = mount['Destination']
        if mount['Type'] == 'volume' and dest not in explicit:
            host['Binds'].append(mount['Name']+':'+dest+('' if mount.get('RW',True) else ':ro'))
    host['Binds'] += old.get('_private_binds', [])
    if candidate:
        host['RestartPolicy'] = {'Name':'no'}
        host['PortBindings'] = {}
        host['PublishAllPorts'] = False
    config['HostConfig'] = host
    endpoints = {}
    for name, network in old['NetworkSettings']['Networks'].items():
        # Let Docker allocate addresses. Never duplicate live container aliases
        # on the candidate or revive a stale dynamic IP from docker inspect.
        endpoint = {}
        if not candidate:
            aliases = network.get('Aliases') or []
            endpoint['Aliases'] = [a for a in aliases if a not in (old['Id'], old['Id'][:12])]
        endpoints[name] = endpoint
    config['NetworkingConfig'] = {'EndpointsConfig': endpoints}
    return config

def healthy(cid):
    deadline = time.monotonic()+100
    while time.monotonic() < deadline:
        state = inspect(cid)['State']
        if state.get('Status') in ('dead','exited','removing'):
            raise RuntimeError('New container exited before readiness.')
        if state.get('Health',{}).get('Status') == 'healthy':
            # Verify the source/config version and required local assets too.
            marker = run('docker','exec',cid,'php','-r',
                         'require "data/config.php"; echo config::VERSION."|".config::DEFAULT_THEME;',capture=True)
            if marker.strip() != '27|Black':
                raise RuntimeError('New source/config version is masked by an old setting or mount.')
            html = run('docker','exec',cid,'curl','-fsS','--max-time','10',
                       'http://127.0.0.1/',capture=True)
            if 'In Code We Trust.' not in html or 'zupt-web.securityops.co' not in html or '<script' in html.lower() or '/static/themes/Black.css?v27' not in html:
                raise RuntimeError('New home page failed its content check.')
            headers = run('docker','exec',cid,'curl','-fsSI','--max-time','10',
                          'http://127.0.0.1/',capture=True).lower()
            if "script-src 'none'" not in headers or "connect-src 'none'" not in headers:
                raise RuntimeError('New home page did not enforce its script-free policy.')
            image_headers = run('docker','exec',cid,'curl','-fsSI','--max-time','10',
                                'http://127.0.0.1/images',capture=True).lower()
            if "script-src 'self'" not in image_headers or "connect-src 'self'" not in image_headers or 'refresh:' in image_headers:
                raise RuntimeError('Image pagination policy failed readiness.')
            script = run('docker','exec',cid,'curl','-fsS','--max-time','10',
                         'http://127.0.0.1/static/images-infinite.js?v27',capture=True)
            if 'IntersectionObserver' not in script or 'createDocumentFragment' not in script:
                raise RuntimeError('Image pagination asset is missing or masked.')
            motion = run('docker','exec',cid,'curl','-fsS','--max-time','10',
                         'http://127.0.0.1/static/images-motion.js?v27',capture=True)
            if 'MutationObserver' not in motion or 'MAX_PLAYING' not in motion:
                raise RuntimeError('Animated preview asset is missing or masked.')
            adapters = run('docker','exec',cid,'php','-r',
                           'require "data/config.php"; require "lib/frontend.php"; require "lib/search_execution.php"; require "lib/image_poster.php"; require "scraper/reddit.php"; require "scraper/binternet.php"; echo class_exists("reddit") && class_exists("provider_http") && class_exists("binternet") && function_exists("securitysearch_theme_picker") && function_exists("image_poster_body") ? "ready" : "missing";',capture=True)
            if adapters.strip() != 'ready':
                raise RuntimeError('New provider/media helpers are missing or masked.')
            return
        time.sleep(2)
    raise RuntimeError('New container did not become healthy within 100 seconds.')

def offline_audit(image, backup):
    """Run every checked-in suite in an isolated disposable container.

    No production environment, volumes, network or private runtime files are
    attached. Test dependencies go into a separate audit image, never production.
    """
    audit_image = image + '-audit'
    with tempfile.TemporaryDirectory(prefix='securitysearch-audit-build-') as temp:
        context = Path(temp)
        (context/'Dockerfile').write_text('FROM '+image+'\nRUN apk add --no-cache python3 nodejs git fish\n')
        run('docker','build','-t',audit_image,str(context))
    cid = run('docker','create','--network','none','--entrypoint','/bin/sh',
              '--workdir',APP,audit_image,'-c','sh scripts/test.sh --keep-going',capture=True).strip()
    if not re.fullmatch(r'[a-f0-9]{64}',cid):
        raise RuntimeError('The isolated audit container could not be created.')
    try:
        # ROOT is the clean git archive extracted by deploy-securitysearch.fish.
        # Copy tests explicitly; production .dockerignore rightly excludes them.
        run('docker','cp',str(ROOT)+'/.',cid+':'+APP)
        result = subprocess.run(['docker','start','--attach',cid],text=True,
                                stdout=subprocess.PIPE,stderr=subprocess.STDOUT)
        (backup/'offline-audit.log').write_text(result.stdout or '')
        code = inspect(cid)['State'].get('ExitCode',1)
        if result.returncode != 0 or code != 0:
            # This isolated suite has no production environment or private data.
            # Preserve the complete log, but also show a bounded failure tail so
            # the SSH launcher does not hide the actual assertion or PHP error.
            tail = '\n'.join((result.stdout or '').splitlines()[-120:])[-24000:]
            print('\n--- Offline audit failure: last 120 lines (max 24,000 characters) ---', file=sys.stderr, flush=True)
            print(tail or '[The audit container produced no output.]', file=sys.stderr, flush=True)
            raise RuntimeError('Offline audit failed; production is unchanged. Inspect '+str(backup/'offline-audit.log'))
        print('All offline release suites passed in the isolated runtime.',flush=True)
    finally:
        run('docker','rm','--force',cid)


def live_binternet_gate(cid, backup):
    """Bounded real request from this candidate's actual network and config."""
    run('docker','cp',str(ROOT/'scripts/provider-probe.php'),cid+':/tmp/securitysearch-provider-probe.php')
    result = run('docker','exec','--workdir',APP,cid,'timeout','35','php','-d','apc.enable_cli=1',
                 '/tmp/securitysearch-provider-probe.php','binternet','images','teste','2',capture=True)
    (backup/'binternet-live.json').write_text(result)
    report = json.loads(result)
    if report.get('status') != 'ok' or report.get('first_count',0) < 1:
        raise RuntimeError('Live Binternet gate failed; production is unchanged. Inspect '+str(backup/'binternet-live.json'))
    print('Binternet returned real results from the VPS candidate; available pagination checked.',flush=True)


# These match service_pool::allowed(). A failed live check never stops production.
REDLIB_ORIGINS = ('https://redlib.privacyredirect.com', 'https://redlib.nadeko.net',
                  'https://redlib.privadency.com')

def set_redlib_primary(old, origin):
    if origin not in REDLIB_ORIGINS:
        raise RuntimeError('Refused an unapproved Redlib destination.')
    env = dict(x.split('=', 1) for x in old['Config'].get('Env', []) if '=' in x)
    env['FOURGET_REDLIB_PRIMARY'] = origin
    old['Config']['Env'] = [k+'='+v for k,v in env.items()]

def live_redlib_gate(cid, backup):
    """Select only an origin returning nonempty parsed feed AND keyword results."""
    run('docker','cp',str(ROOT/'scripts/redlib-probe.php'),cid+':/tmp/securitysearch-redlib-probe.php')
    try:
        raw = run('docker','exec','--workdir',APP,cid,'timeout','40','php','-d','apc.enable_cli=1',
                  '/tmp/securitysearch-redlib-probe.php',capture=True)
        report = json.loads(raw)
    except Exception:
        report = {'status':'unavailable','reason':'probe_execution_failed'}
    (backup/'redlib-live.json').write_text(json.dumps(report, indent=2))
    origin = report.get('origin') if isinstance(report,dict) else None
    rows = report.get('attempts',[]) if isinstance(report,dict) else []
    row = rows[-1] if isinstance(rows,list) and rows and isinstance(rows[-1],dict) else {}
    if (not isinstance(report,dict) or report.get('status') != 'ok' or origin not in REDLIB_ORIGINS
        or row.get('origin') != origin or row.get('status') != 'ok'
        or any(type(row.get(k)) is not int or not 1 <= row[k] <= 25 for k in ('feed_count','search_count'))):
        raise RuntimeError('No approved Redlib instance passed both feed and keyword search; production is unchanged. Inspect '+str(backup/'redlib-live.json'))
    print('Verified Redlib feed and keyword search from candidate: '+origin,flush=True)
    return origin

def verify_redlib_config(cid, origin):
    effective = run('docker','exec','--workdir',APP,cid,'php','-r',
                    'require "data/config.php"; require "lib/service_pool.php"; echo service_pool::primary();',capture=True).strip()
    if effective != origin:
        raise RuntimeError('The replacement did not retain the verified Redlib primary.')


def validate_operator_pack(directory):
    """Load only the sibling validator, even when this script is imported by path.

    Do not modify sys.path or import a same-named module from the caller's CWD,
    PYTHONPATH or sys.modules. A missing/unsafe helper fails before Docker work.
    """
    helper = Path(__file__).resolve().with_name('operator_themes.py')
    if helper.is_symlink() or not helper.is_file():
        raise RuntimeError('Required deployment helper is missing or unsafe: scripts/operator_themes.py')
    spec = importlib.util.spec_from_file_location('_securitysearch_deploy_operator_themes', helper)
    if spec is None or spec.loader is None:
        raise RuntimeError('Cannot load the bundled operator-theme validator.')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    validator = getattr(module, 'validate', None)
    if not callable(validator):
        raise RuntimeError('Bundled operator-theme validator has no validate function.')
    return validator(directory)


def main():
    if os.geteuid() != 0:
        raise RuntimeError('Run this updater as root on the IONOS Docker host.')
    operator_pack = ROOT/'static/operator-themes'
    if operator_pack.is_symlink() or operator_pack.exists():
        validate_operator_pack(operator_pack)
    for program in ('docker','curl','flock'):
        if shutil.which(program) is None:
            raise RuntimeError('Required host program is missing: '+program)
    os.umask(0o077)
    lock = open(LOCK_PATH,'w')
    fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
    name = os.environ.get('SECURITYSEARCH_CONTAINER','security-search')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]*',name):
        raise RuntimeError('Invalid existing container name.')
    old = inspect(name)
    if not old['State'].get('Running'):
        raise RuntimeError('The existing container must be running before this update.')
    host = old['HostConfig']
    if host.get('AutoRemove') or host.get('Privileged') or host.get('NetworkMode') in ('host','none') or host.get('NetworkMode','').startswith('container:'):
        raise RuntimeError('This updater requires a normal retained container on Docker bridge networks.')
    for network in old['NetworkSettings']['Networks'].values():
        if network.get('IPAMConfig'):
            raise RuntimeError('Explicit static container IP detected; prepare a network-specific cutover before using this updater.')
    ports = host.get('PortBindings') or {}
    if ports.get('80/tcp') != [{'HostIp':'172.17.0.1','HostPort':'5140'}]:
        raise RuntimeError('Expected the reported 172.17.0.1:5140 -> 80 binding. Existing container was not changed.')
    for mount in old.get('Mounts',[]):
        dest = mount['Destination'].rstrip('/')
        allowed = [APP+'/data/api_keys', APP+'/data/proxies', APP+'/data/captcha', APP+'/icons', APP+'/banner']
        masks_app = dest in ('/', '/var', '/var/www', '/var/www/html', APP) or dest.startswith(APP+'/')
        safe_data = any(dest == base or dest.startswith(base+'/') for base in allowed)
        masks_runtime = dest in ('', '/', '/etc', '/usr', '/usr/local') or any(dest == base or dest.startswith(base+'/') for base in ('/etc/apache2','/etc/php84','/etc/ImageMagick-7','/usr/lib','/bin','/usr/local/etc','/usr/local/bin','/usr/bin','/sbin'))
        if masks_runtime or (masks_app and not safe_data):
            raise RuntimeError('An existing source/configuration mount would mask the update. Existing container was not changed.')
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    backup = BACKUP_ROOT/stamp
    backup.mkdir(parents=True, mode=0o700)
    (backup/'container.json').write_text(json.dumps(old,indent=2))
    (backup/'source-directory.txt').write_text(str(ROOT)+'\n')
    image = 'security-search:v'+VERSION+'-'+stamp.lower()
    restart_policy = copy.deepcopy(host.get('RestartPolicy') or {'Name':'no'})
    restart_option = restart_policy.get('Name') or 'no'
    if restart_option == 'on-failure' and restart_policy.get('MaximumRetryCount',0):
        restart_option += ':'+str(restart_policy['MaximumRetryCount'])
    rollback_name = name+'-rollback-'+stamp.lower()
    candidate_name = name+'-candidate-'+stamp.lower()
    # Preserve the effective PHP configuration even when operators edited it
    # inside the old container. Never display this credential-bearing output.
    raw = run('docker','exec',old['Id'],'php','-r',
              'require "data/config.php"; echo json_encode((new ReflectionClass("config"))->getConstants(), JSON_THROW_ON_ERROR);',capture=True)
    effective = json.loads(raw)
    env = dict(item.split('=',1) for item in old['Config'].get('Env',[]) if '=' in item)
    for key,value in effective.items():
        if key in ('VERSION','CAPTCHA_DATASET'):
            continue
        if value is None:
            continue
        if isinstance(value, list):
            if any(not isinstance(x,str) for x in value):
                raise RuntimeError('Unsupported nested configuration value; existing container was not changed.')
            value = ','.join(value)
        elif isinstance(value,bool):
            value = 'true' if value else 'false'
        else:
            value = str(value)
        env['FOURGET_'+key] = value
    # This release explicitly migrates the instance default requested by the owner.
    # Browser theme cookies remain user choices; all other effective settings persist.
    env['FOURGET_DEFAULT_THEME'] = 'Black'
    env.pop('FOURGET_VERSION',None)
    old['Config']['Env'] = [k+'='+v for k,v in env.items()]
    # Migrate away from the failed self-hosted default before candidate startup.
    set_redlib_primary(old, env.get('FOURGET_REDLIB_PRIMARY') if env.get('FOURGET_REDLIB_PRIMARY') in REDLIB_ORIGINS else REDLIB_ORIGINS[0])
    (backup/'effective-config.json').write_text(raw)
    old['_private_binds'] = []
    for directory in ('api_keys','proxies','captcha'):
        destination = APP+'/data/'+directory
        # Retain existing mounts. Otherwise snapshot private container data and
        # bind that snapshot read-only in both the candidate and replacement.
        if any(m['Destination'].rstrip('/')==destination for m in old.get('Mounts',[])):
            continue
        exists = subprocess.run(['docker','exec',old['Id'],'test','-d',destination],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        if exists.returncode == 0:
            target = backup/'data'/directory
            target.parent.mkdir(exist_ok=True)
            run('docker','cp','-a',old['Id']+':'+destination,str(target))
            old['_private_binds'].append(str(target)+':'+destination+':ro')
    print('Building the new image while the current service stays online.',flush=True)
    run('docker','build','--pull','-t',image,str(ROOT))
    offline_audit(image,backup)
    candidate = None
    replacement = None
    stopped = False
    renamed = False
    committed = False
    try:
        candidate = api('POST','/containers/create?name='+candidate_name,create_payload(old,image,True))['Id']
        api('POST','/containers/'+candidate+'/start')
        healthy(candidate)
        selected_redlib = live_redlib_gate(candidate,backup)
        set_redlib_primary(old, selected_redlib)
        live_binternet_gate(candidate,backup)
        api('DELETE','/containers/'+candidate+'?force=1')
        candidate = None
        # Store the recovery command before stopping production.
        rollback = backup/'rollback.sh'
        import shlex
        q=shlex.quote
        rollback.write_text('#!/bin/sh\nset -eu\n'+
            'exec 9>'+q(LOCK_PATH)+'\nflock -n 9 || { echo "Another update or rollback is running." >&2; exit 1; }\n'+
            'old_id='+q(old['Id'])+'\nname='+q(name)+'\n'+
            'docker inspect "$old_id" >/dev/null\n'+
            "current_id=$(docker inspect --format '{{.Id}}' \"$name\" 2>/dev/null || true)\n"+
            'if [ "$current_id" = "$old_id" ]; then docker update --restart='+q(restart_option)+' "$old_id" >/dev/null; docker start "$old_id"; exit 0; fi\n'+
            'if [ -n "$current_id" ]; then\n'+
            "  current_image=$(docker inspect --format '{{.Config.Image}}' \"$current_id\")\n"+
            '  [ "$current_image" = '+q(image)+' ] || { echo "The running container belongs to a different update." >&2; exit 1; }\n'+
            '  docker update --restart=no "$current_id" >/dev/null\n'+
            '  docker stop "$current_id"\n'+
            '  docker rename "$current_id" '+q(name+'-failed-'+stamp.lower())+'\nfi\n'+
            'docker rename "$old_id" "$name"\n'+
            'docker update --restart='+q(restart_option)+' "$old_id" >/dev/null\n'+
            'docker start "$old_id"\n')
        rollback.chmod(0o700)
        print('Candidate passed readiness. Replacing the service; this causes a short interruption.',flush=True)
        stopped = True
        # A retained rollback container must not restart after a Docker reboot.
        api('POST','/containers/'+old['Id']+'/update',{'RestartPolicy':{'Name':'no'}})
        api('POST','/containers/'+old['Id']+'/stop?t=30')
        api('POST','/containers/'+old['Id']+'/rename?name='+rollback_name)
        renamed = True
        replacement = api('POST','/containers/create?name='+urllib.parse.quote(name,safe=''),create_payload(old,image))['Id']
        api('POST','/containers/'+replacement+'/start')
        healthy(replacement)
        verify_redlib_config(replacement, selected_redlib)
        # Verify the preserved host binding, beyond container-internal health.
        run('curl','-fsS','--max-time','10','-o','/dev/null','http://172.17.0.1:5140/')
        committed = True
        (backup/'release.json').write_text(json.dumps({'image':image,'container':replacement,'rollback_container':rollback_name,'redlib_primary':selected_redlib},indent=2))
        print('Deployment healthy at 172.17.0.1:5140. NPM upstream remains unchanged.')
        print('Rollback: bash '+str(rollback))
        print('Retain '+str(backup)+'; the running service may mount its private data snapshots.')
    finally:
        recovery_errors = []
        def attempt(method, path, data=None):
            try:
                api(method,path,data)
            except Exception:
                recovery_errors.append(method+' '+path.split('?')[0])
        # Reconcile names when Docker completed a mutation but its response was
        # lost. Only this release's exact image + label may be cleaned up.
        if candidate is None:
            try:
                candidate = owned_container(candidate_name,image)
            except Exception:
                recovery_errors.append('candidate reconciliation')
        if candidate:
            attempt('DELETE','/containers/'+candidate+'?force=1')
        if stopped and not committed:
            print('Cutover failed; restoring the previous container.',file=sys.stderr)
            if replacement is None:
                try:
                    current = find_container(name)
                    if current is not None and current['Id'] != old['Id']:
                        replacement = owned_container(name,image)
                except Exception:
                    recovery_errors.append('replacement reconciliation')
            if replacement:
                attempt('POST','/containers/'+replacement+'/stop?t=10')
                attempt('DELETE','/containers/'+replacement+'?force=1')
            try:
                actual = inspect(old['Id'])
                renamed = actual.get('Name','/'+(rollback_name if renamed else name)) != '/'+name
            except Exception:
                renamed = True
            if renamed:
                attempt('POST','/containers/'+old['Id']+'/rename?name='+urllib.parse.quote(name,safe=''))
            attempt('POST','/containers/'+old['Id']+'/update',{'RestartPolicy':restart_policy})
            attempt('POST','/containers/'+old['Id']+'/start')
            if recovery_errors:
                print('Some recovery operations failed. Run: bash '+str(backup/'rollback.sh'),file=sys.stderr)
            else:
                print('Previous container restarted. Inspect its health before retrying.',file=sys.stderr)
        elif recovery_errors:
            print('Candidate cleanup needs attention: '+str(candidate),file=sys.stderr)

if __name__ == '__main__':
    def interrupted(signum, frame):
        raise KeyboardInterrupt()
    signal.signal(signal.SIGTERM,interrupted)
    signal.signal(signal.SIGHUP,interrupted)
    try:
        main()
    except (Exception, KeyboardInterrupt) as error:
        # Do not print subprocess stderr, inspect data or environment values.
        print('Update stopped: '+(str(error) if isinstance(error,RuntimeError) else type(error).__name__),file=sys.stderr)
        sys.exit(1)
