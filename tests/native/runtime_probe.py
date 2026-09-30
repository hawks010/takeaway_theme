"""Compare repeated native checkout loads in a disposable fixture, without submitting orders."""
import argparse
import http.cookiejar
import json
import os
import re
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
parser.add_argument('--repeats', type=int, default=10)
parser.add_argument('--evidence-dir', required=True)
parser.add_argument('--gdb', action='store_true')
args = parser.parse_args()
base = os.environ.get('TTOS_TEST_BASE_URL', 'http://127.0.0.1:8080').rstrip('/')
target = urllib.parse.urlsplit(base)
if target.scheme != 'http' or target.hostname != '127.0.0.1' or target.path or target.username or target.password or target.query or target.fragment:
    raise RuntimeError('Refusing a non-local HTTP fixture')
if not 1 <= args.repeats <= 50:
    raise RuntimeError('Use 1 to 50 checkout loads per mode')
fixture = json.loads(Path('/tmp/ttos-fixtures.json').read_text())
if not fixture['checkout_url'].startswith(base + '/'):
    raise RuntimeError('Fixture checkout URL does not match the local target')
evidence = Path(args.evidence_dir)
evidence.mkdir(parents=True, exist_ok=True)

def wp(*command, check=True):
    return subprocess.run([args.php, '-d', 'memory_limit=512M', args.wp_cli,
                           '--no-color', '--path=' + str(Path(args.wp_path).resolve()),
                           *command], check=check, capture_output=True, text=True, timeout=60)

guard = wp('eval', 'if (!defined("TTOS_FIXTURE_ONLY") || TTOS_FIXTURE_ONLY !== true || wp_get_environment_type() !== "local") { WP_CLI::error("Not a disposable fixture"); } echo "TTOS_LOCAL_FIXTURE";')
if 'TTOS_LOCAL_FIXTURE' not in guard.stdout:
    raise RuntimeError('Disposable fixture guard failed')
with socket.socket() as port_check:
    port_check.bind(('127.0.0.1', target.port or 80))
initially_active = wp('plugin', 'is-active', 'takeaway-os', check=False).returncode == 0
results = []
failures = []
try:
    for mode in ('disabled', 'enabled'):
        wp('plugin', 'activate' if mode == 'enabled' else 'deactivate', 'takeaway-os')
        with (evidence / (mode + '-server.log')).open('w') as server_log:
            server_command = [args.php, '-d', 'memory_limit=512M', '-d', 'max_execution_time=30',
                              '-S', target.netloc, '-t', str(Path(args.wp_path).resolve())]
            if args.gdb:
                server_command = ['gdb', '--batch', '-ex', 'set pagination off',
                                  '-ex', 'set breakpoint pending on', '-ex', 'break zend_timeout',
                                  '-ex', 'run', '-ex', 'thread apply all bt full', '--args', *server_command]
            server = subprocess.Popen(server_command, stdout=server_log, stderr=subprocess.STDOUT,
                                      start_new_session=True)
            try:
                for attempt in range(30):
                    try:
                        with urllib.request.urlopen(base + '/wp-login.php', timeout=40) as response:
                            response.read()
                        break
                    except OSError:
                        if server.poll() is not None or attempt == 29:
                            raise
                        time.sleep(0.1)
                for iteration in range(1, args.repeats + 1):
                    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
                    started = time.monotonic()
                    with client.open(base + '/?add-to-cart=' + str(fixture['product_id']), timeout=40) as response:
                        response.read()
                    with client.open(fixture['checkout_url'], timeout=40) as response:
                        body = response.read().decode()
                        if response.status != 200 or not re.search(r'name="woocommerce-process-checkout-nonce"', body):
                            raise RuntimeError('Native checkout did not render')
                        if 'Fatal error' in body or 'critical error' in body.lower():
                            raise RuntimeError('Checkout contains a PHP failure')
                    result = {'mode': mode, 'iteration': iteration, 'seconds': round(time.monotonic() - started, 3)}
                    results.append(result)
                    print(json.dumps(result), flush=True)
            except Exception as error:
                failure = {'mode': mode, 'error': str(error), 'server_exit': server.poll()}
                failures.append(failure)
                print(json.dumps(failure), flush=True)
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
finally:
    wp('plugin', 'activate' if initially_active else 'deactivate', 'takeaway-os')
    (evidence / 'checkout-loads.json').write_text(json.dumps({'loads': results, 'failures': failures}, indent=2) + '\n')
if failures:
    raise SystemExit(1)
