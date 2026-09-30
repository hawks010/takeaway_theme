"""Native HTTP smoke test. Only a disposable localhost WordPress fixture is accepted."""
import html, http.cookiejar, json, os, re, time, urllib.parse, urllib.request
from pathlib import Path
base = os.environ.get('TTOS_TEST_BASE_URL', 'http://127.0.0.1:8080').rstrip('/')
target = urllib.parse.urlsplit(base)
if target.scheme != 'http' or target.hostname != '127.0.0.1' or target.path or target.username or target.password or target.query or target.fragment:
    raise RuntimeError('Refusing a non-local HTTP fixture')
fixture = json.loads(Path('/tmp/ttos-fixtures.json').read_text())
for key in ('checkout_url','cart_url'):
    if not fixture[key].startswith(base+'/'):
        raise RuntimeError('Refusing a non-local checkout target')
def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def fetch(c, url, data=None):
    if not url.startswith(base+'/'):
        raise RuntimeError('Refusing a non-local HTTP target')
    return c.open(url, None if data is None else urllib.parse.urlencode(data,doseq=True).encode(), timeout=40)
for attempt in range(30):
    try:
        fetch(client(),base+'/wp-login.php').read();break
    except OSError:
        time.sleep(.3)
owner=client()
fetch(owner,base+'/wp-login.php').read()
response=fetch(owner,base+'/wp-login.php',{'log':'fixtureowner','pwd':os.environ['TEST_OWNER_PASSWORD'],'wp-submit':'Log In','redirect_to':base+'/wp-admin/admin.php?page=takeaway-os','testcookie':'1'})
body=response.read().decode()
if 'wp-login.php' in response.url:
    errors = re.findall(r'<div[^>]+id=[\"\']login_error[\"\'][^>]*>(.*?)</div>', body, re.S)
    error = html.unescape(re.sub(r'<[^>]+>', ' ', ' '.join(errors))).strip()
    raise AssertionError('Owner could not log in: ' + (error or 'No login error markup; final URL ' + response.url))
pages = ['takeaway-os','takeaway-os-orders','takeaway-os-kitchen','takeaway-os-customers',
         'takeaway-os-reports','takeaway-os-settings','takeaway-os-launchpad','takeaway-os-setup-health',
         'takeaway-os-menu','takeaway-os-site-content','takeaway-os-delivery','takeaway-os-payments',
         'takeaway-os-operations','takeaway-os-golive','takeaway-os-production']
for slug in pages:
    response=fetch(owner,base+'/wp-admin/admin.php?page='+slug)
    body=response.read().decode()
    assert response.status==200 and 'critical error' not in body.lower() and 'Fatal error' not in body, slug
    assert urllib.parse.parse_qs(urllib.parse.urlsplit(response.url).query).get('page') == [slug], 'Unexpected redirect: '+slug
    print('PASS authenticated owner screen '+slug)
nonce=json.loads(fetch(owner,base+'/wp-admin/admin.php?page=takeaway-os&ttos_fixture_rest_nonce=1').read().decode())['nonce']
req=urllib.request.Request(base+'/?rest_route=/ttos/v1/accounting/export',data=json.dumps({'from':'2026-01-01','to':'2026-01-02','provider':'generic'}).encode(),headers={'Content-Type':'application/json','X-WP-Nonce':nonce},method='POST')
export=json.loads(owner.open(req,timeout=40).read().decode())
assert export.get('success') is True, 'Export request did not succeed'
response=fetch(owner,export['download_url'])
body=response.read().decode('utf-8-sig')
assert body.startswith('order_id,'), 'Owner download was blocked or redirected'
assert 'customer_email' in body
print('PASS owner authenticated accounting download without CRM redirect')
try:
    response=fetch(client(),export['download_url'])
    assert not response.read().decode('utf-8-sig').startswith('order_id,'), 'Anonymous download exposed orders'
except urllib.error.HTTPError as e:
    assert e.code in (400,401,403)
print('PASS anonymous accounting download denied')
guest=client()
fetch(guest,base+'/?add-to-cart='+str(fixture['product_id'])).read()
checkout=fetch(guest,fixture['checkout_url']).read().decode()
match=re.search(r'name="woocommerce-process-checkout-nonce"[^>]*value="([^"]+)"',checkout)
assert match, 'Native checkout nonce not found; no test order was submitted'
shipping=re.search(r'name="shipping_method\[0\]"[^>]*value="([^"]+)"',checkout)
assert shipping, 'Native shipping choice not rendered'
data={'woocommerce-process-checkout-nonce':match[1],'billing_first_name':'Fixture','billing_last_name':'Customer','billing_country':'GB','billing_address_1':'1 Example Street','billing_city':'London','billing_postcode':'SW1A 1AA','billing_phone':'02079460000','billing_email':'checkout@example.test','payment_method':'ttos_fixture','shipping_method[0]':shipping[1],'ttos_fulfilment_method':'collection','ttos_requested_time':'asap','terms':'on','terms-field':'1'}
result=json.loads(fetch(guest,base+'/?wc-ajax=checkout',data).read().decode())
assert result.get('result')=='success', json.dumps(result)
assert result['redirect'].startswith(base+'/')
print('PASS native classic checkout accepted through offline fixture gateway')
Path('/tmp/ttos-http-result.json').write_text(json.dumps({'checkout_redirect':result['redirect']}))
