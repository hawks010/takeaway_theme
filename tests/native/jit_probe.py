"""Compare PHP/PCRE JIT using complete HTTP smoke tests in a guarded local fixture."""
import argparse
import json
import os
import signal
import socket
import subprocess
import time
import urllib.parse
import urllib.request
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--wp-path', required=True)
parser.add_argument('--wp-cli', required=True)
parser.add_argument('--php', default='php')
parser.add_argument('--repeats', type=int, default=3)
parser.add_argument('--evidence-dir', required=True)
args = parser.parse_args()
base = os.environ.get('TTOS_TEST_BASE_URL', 'http://127.0.0.1:8080').rstrip('/')
target = urllib.parse.urlsplit(base)
if target.scheme != 'http' or target.hostname != '127.0.0.1' or target.path or target.username or target.password or target.query or target.fragment:
    raise RuntimeError('Refusing a non-local HTTP fixture')
if not 1 <= args.repeats <= 10:
    raise RuntimeError('Use 1 to 10 HTTP smoke tests per mode')
wp_path = str(Path(args.wp_path).resolve())
wp_command = [args.php, '-d', 'memory_limit=512M', args.wp_cli, '--path=' + wp_path]
guard = subprocess.run([*wp_command, 'eval', 'if (!defined("TTOS_FIXTURE_ONLY") || TTOS_FIXTURE_ONLY !== true || wp_get_environment_type() !== "local") { WP_CLI::error("Not a disposable fixture"); } echo "TTOS_LOCAL_FIXTURE";'],
                       check=True, capture_output=True, text=True, timeout=60)
if 'TTOS_LOCAL_FIXTURE' not in guard.stdout:
    raise RuntimeError('Disposable fixture guard failed')
subprocess.run([*wp_command, 'plugin', 'is-active', 'takeaway-os'], check=True, capture_output=True, timeout=60)
with socket.socket() as port_check:
    port_check.bind(('127.0.0.1', target.port or 80))
evidence = Path(args.evidence_dir)
evidence.mkdir(parents=True, exist_ok=True)
results = []
modes = {'default': [], 'php-jit-off': ['-d', 'opcache.jit=disable'], 'pcre-jit-off': ['-d', 'pcre.jit=0']}
try:
    for mode, ini_flags in modes.items():
        for iteration in range(1, args.repeats + 1):
            name = mode + '-' + str(iteration)
            result = {'mode': mode, 'iteration': iteration}
            started = time.monotonic()
            with (evidence / (name + '-server.log')).open('w') as server_log:
                server = subprocess.Popen([args.php, '-d', 'memory_limit=512M', '-d', 'max_execution_time=30',
                                           *ini_flags, '-S', target.netloc, '-t', wp_path],
                                          stdout=server_log, stderr=subprocess.STDOUT, start_new_session=True)
                try:
                    for attempt in range(30):
                        try:
                            with urllib.request.urlopen(base + '/?ttos_fixture_runtime=1', timeout=40) as response:
                                result['runtime'] = json.loads(response.read())
                            break
                        except OSError:
                            if server.poll() is not None or attempt == 29:
                                raise
                            time.sleep(0.1)
                    assert result['runtime']['sapi'] == 'cli-server'
                    if mode == 'php-jit-off':
                        assert not (result['runtime']['jit'] or {}).get('enabled'), 'PHP JIT remained enabled'
                    if mode == 'pcre-jit-off':
                        assert result['runtime']['ini']['pcre.jit'] == '0', 'PCRE JIT remained enabled'
                    with (evidence / (name + '-http.log')).open('w') as http_log:
                        smoke = subprocess.run(['python3', '-u', str(Path(__file__).with_name('http_smoke.py'))],
                                               stdout=http_log, stderr=subprocess.STDOUT, timeout=180)
                    result['http_exit'] = smoke.returncode
                    if smoke.returncode == 0:
                        with (evidence / (name + '-verify.log')).open('w') as verify_log:
                            verify = subprocess.run([*wp_command, 'eval-file', str(Path(__file__).with_name('verify_checkout.php').resolve())],
                                                    stdout=verify_log, stderr=subprocess.STDOUT, timeout=60)
                        result['verify_exit'] = verify.returncode
                        result['passed'] = verify.returncode == 0
                    else:
                        result['passed'] = False
                    result['server_exit'] = server.poll()
                except Exception as error:
                    result.update({'passed': False, 'error': str(error), 'server_exit': server.poll()})
                finally:
                    try:
                        os.killpg(server.pid, signal.SIGTERM)
                    except ProcessLookupError:
                        pass
                    try:
                        server.wait(timeout=5)
                    except subprocess.TimeoutExpired:
                        os.killpg(server.pid, signal.SIGKILL)
                        server.wait()
            result['seconds'] = round(time.monotonic() - started, 3)
            results.append(result)
            print(json.dumps(result), flush=True)
finally:
    (evidence / 'jit-comparison.json').write_text(json.dumps(results, indent=2) + '\n')
if any(not result['passed'] for result in results):
    raise SystemExit(1)
