"""Real theme form -> cart -> checkout preference, on a disposable loopback shop.
No orders are submitted. Native shipping rates and payment methods are not rewritten.
"""
import http.cookiejar, json, os, re, subprocess, urllib.error, urllib.parse, urllib.request
from html.parser import HTMLParser
from pathlib import Path
base=os.environ.get('TTOS_TEST_BASE_URL','http://127.0.0.1:8080').rstrip('/')
url=urllib.parse.urlsplit(base)
if url.scheme!='http' or url.hostname not in ('127.0.0.1','localhost') or url.path:
    raise RuntimeError('Use a disposable loopback fixture')
fixture=json.loads(Path('/tmp/ttos-fixtures.json').read_text())
wp_path=os.environ.get('WP_PATH','/tmp/ttos-wp')
wp_cli=os.environ.get('TTOS_WP_CLI','/tmp/wp-cli.phar')
def wp(code):
    return subprocess.check_output(['php',wp_cli,'--path='+wp_path,'eval',
        "if (!defined('TTOS_FIXTURE_ONLY') || !TTOS_FIXTURE_ONLY || wp_get_environment_type() !== 'local') throw new RuntimeException('Fixture only'); "+code],text=True)
wp('$id = '+str(fixture['product_id'])+'; $term = term_exists("fixture-menu", "product_cat") ?: wp_insert_term("Fixture menu", "product_cat", array("slug"=>"fixture-menu")); $p = wc_get_product($id); $p->set_category_ids(array((int)$term["term_id"])); $p->save();')
shop=wp('echo wc_get_page_permalink("shop");').strip()
assert shop.startswith(base+'/'), shop
class Page(HTMLParser):
    def __init__(self,text):
        super().__init__(); self.forms=[];self.form=None;self.selects={};self.select=None;self.feed(text)
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='form': self.form={'attrs':a,'fields':{},'buttons':[]};self.forms.append(self.form)
        if tag=='input' and self.form is not None and a.get('name') and a.get('type') not in ('checkbox','radio','submit'):
            self.form['fields'][a['name']]=a.get('value','')
        if tag=='button' and self.form is not None:self.form['buttons'].append(a)
        if tag=='select':self.select=a.get('name');self.selects[self.select]=[]
        if tag=='option' and self.select:self.selects[self.select].append(a)
    def handle_endtag(self,tag):
        if tag=='form':self.form=None
        if tag=='select':self.select=None
    def selection_form(self):
        return next(f for f in self.forms if f['fields'].get('ttos_action')=='select_fulfilment')
    def selected(self):
        return next(b['value'] for b in self.selection_form()['buttons'] if b.get('aria-pressed')=='true')
    def checkout_choice(self):
        choices=self.selects['ttos_fulfilment_method']
        return next((c['value'] for c in choices if 'selected' in c),choices[0]['value'])
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def page(c,target,data=None):
    assert target.startswith(base+'/')
    request=urllib.request.Request(target,data=None if data is None else urllib.parse.urlencode(data).encode())
    with c.open(request,timeout=40) as response:
        assert response.url.startswith(base+'/')
        return Page(response.read().decode())
def choose(c,method):
    before=page(c,shop);form=before.selection_form();fields=dict(form['fields']);fields['ttos_fulfilment_method']=method
    result=page(c,urllib.parse.urljoin(shop,form['attrs'].get('action','')),fields)
    assert result.selected()==method
    assert page(c,shop).selected()==method
    print('PASS menu persists '+method+' after form submission and refresh')
    return result
visitor=client();menu=choose(visitor,'collection')
fresh=page(client(),shop);assert fresh.selected()=='delivery'
print('PASS separate guest retains their own default')
product_form=next(f for f in menu.forms if f['fields'].get('add-to-cart')==str(fixture['product_id']))
fields=dict(product_form['fields']);fields['quantity']='1'
page(visitor,shop,fields)
assert page(visitor,fixture['cart_url']).forms
assert page(visitor,fixture['checkout_url']).checkout_choice()=='collection'
assert page(visitor,shop).selected()=='collection'
print('PASS actual menu-item form, cart, checkout and return to menu retain collection')
for bad_data,status in [({'ttos_fulfilment_method':'delivery','ttos_fulfilment_nonce':'invalid'},403),({'ttos_fulfilment_method':'unknown'},400)]:
    form=page(visitor,shop).selection_form();data=dict(form['fields']);data.update(bad_data)
    try:page(visitor,shop,data);raise AssertionError('Invalid selection unexpectedly accepted')
    except urllib.error.HTTPError as error:assert error.code==status
    assert page(visitor,shop).selected()=='collection'
    print('PASS rejected request does not change collection ('+str(status)+')')
choose(visitor,'delivery');assert page(visitor,fixture['checkout_url']).checkout_choice()=='delivery'
print('PASS switching back to delivery updates checkout')
with visitor.open(fixture['checkout_url'],timeout=40) as response:text=response.read().decode()
params=json.loads(re.search(r'var wc_checkout_params\s*=\s*(\{.*?\});',text,re.S).group(1))
body={'security':params['update_order_review_nonce'],'post_data':'ttos_fulfilment_method=collection','country':'GB','state':'','postcode':'SW1A 1AA','city':'London','address':'1 Example Street','s_country':'GB','s_state':'','s_postcode':'SW1A 1AA','s_city':'London','s_address':'1 Example Street','has_full_address':'true'}
with visitor.open(base+'/?wc-ajax=update_order_review',urllib.parse.urlencode(body).encode(),timeout=40) as response:
    data=json.loads(response.read().decode());assert data.get('result')=='success', data
assert page(visitor,shop).selected()=='collection'
assert page(visitor,fixture['checkout_url']).checkout_choice()=='collection'
print('PASS native checkout review choice persists back to the menu')
print('FULFILMENT HTTP complete: real theme, native form submissions, no order submitted')
