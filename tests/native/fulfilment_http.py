"""Real theme form -> cart -> checkout preference, on a disposable loopback shop.
No orders are submitted. Native shipping rates and payment methods are not rewritten.
"""
import base64, http.cookiejar, json, os, re, subprocess, urllib.error, urllib.parse, urllib.request
from html.parser import HTMLParser
from pathlib import Path
base=os.environ.get('TTOS_TEST_BASE_URL','http://127.0.0.1:8080').rstrip('/')
url=urllib.parse.urlsplit(base)
if url.scheme!='http' or url.hostname not in ('127.0.0.1','localhost') or url.path or url.username or url.password or url.query or url.fragment:
    raise RuntimeError('Use a disposable loopback fixture')
fixture=json.loads(Path('/tmp/ttos-fixtures.json').read_text())
wp_path=os.environ.get('WP_PATH','/tmp/ttos-wp')
wp_cli=os.environ.get('TTOS_WP_CLI','/tmp/wp-cli.phar')
def wp(code):
    return subprocess.check_output(['php','-d','memory_limit=512M','-d','error_reporting=24575',wp_cli,'--path='+wp_path,'eval',
        "if (!defined('TTOS_FIXTURE_ONLY') || !TTOS_FIXTURE_ONLY || wp_get_environment_type() !== 'local') throw new RuntimeException('Fixture only'); "+code],text=True)
wp('if (untrailingslashit(home_url()) !== '+json.dumps(base)+') throw new RuntimeException("Fixture URL mismatch");')
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
before=json.loads(wp('echo wp_json_encode(array("hours"=>TTOS_Site_Content::get("opening_times"),"checkout"=>TTOS_Operations::get("checkout")));'))
try:
    tomorrow=wp('$hours=TTOS_Site_Content::get("opening_times");$hours["override"]="normal";$hours["temporary_closure"]="0";foreach($hours["days"] as &$day){$day=array_merge($day,array("closed"=>"0","open"=>"08:00","close"=>"23:00","collection_open"=>"08:00","collection_close"=>"23:00","delivery_open"=>"16:00","delivery_close"=>"23:00"));}unset($day);TTOS_Site_Content::update_section("opening_times",$hours);TTOS_Operations::update_section("checkout",array_merge(TTOS_Operations::get("checkout"),array("time_mode"=>"slot","preorder_enabled"=>"1","lead_time_collection"=>"0","lead_time_delivery"=>"0","max_days_ahead"=>"2")));echo (new DateTimeImmutable("tomorrow",wp_timezone()))->format("Y-m-d");').strip()
    def review(method,requested):
        body['post_data']=urllib.parse.urlencode({'ttos_fulfilment_method':method,'ttos_requested_time':requested})
        with visitor.open(base+'/?wc-ajax=update_order_review',urllib.parse.urlencode(body).encode(),timeout=40) as response:
            result=json.loads(response.read().decode())
        assert result.get('result')=='success',result
        assert '.woocommerce-checkout-review-order-table' in result['fragments']
        assert '.woocommerce-checkout-payment' in result['fragments']
        return Page(result['fragments']['#ttos_requested_time_field'])
    morning=tomorrow+'T08:00'; evening=tomorrow+'T20:00'
    collection=review('collection',morning).selects['ttos_requested_time']
    assert any(o['value']==morning and 'selected' in o for o in collection)
    delivery=review('delivery',morning).selects['ttos_requested_time']
    assert all(o['value']!=morning for o in delivery)
    assert next(o['value'] for o in delivery if 'selected' in o)==''
    print('PASS native AJAX refresh replaces collection-only slots and clears unavailable scheduled time')
    delivery=review('delivery',evening).selects['ttos_requested_time']
    assert any(o['value']==evening and 'selected' in o for o in delivery)
    collection=review('collection',evening).selects['ttos_requested_time']
    assert any(o['value']==evening and 'selected' in o for o in collection)
    assert any(o['value']==morning for o in collection)
    print('PASS reverse AJAX selection restores collection slots and retains a still-valid requested time')
    assert page(visitor,shop).selected()=='collection'
    assert page(visitor,fixture['checkout_url']).checkout_choice()=='collection'
    print('PASS refreshed slot fragments retain menu/checkout preference persistence')
finally:
    payload=base64.b64encode(json.dumps(before).encode()).decode()
    wp('$before=json_decode(base64_decode("'+payload+'"),true);TTOS_Site_Content::update_section("opening_times",$before["hours"]);TTOS_Operations::update_section("checkout",$before["checkout"]);')
order_count=wp('echo wc_get_orders(array("limit"=>1,"paginate"=>true))->total;').strip()
def update_totals(method,valid_nonce=True):
    checkout=page(visitor,fixture['checkout_url'])
    form=next(f for f in checkout.forms if 'woocommerce-process-checkout-nonce' in f['fields'])
    fields=dict(form['fields'])
    fields.update({'woocommerce_checkout_update_totals':'1','ttos_fulfilment_method':method,'ttos_requested_time':'asap','billing_first_name':'Fixture','billing_last_name':'Guest','billing_country':'GB','billing_address_1':'1 Example Street','billing_city':'London','billing_postcode':'SW1A 1AA','billing_email':'guest@example.test','payment_method':'ttos_fixture'})
    if not valid_nonce: fields['woocommerce-process-checkout-nonce']='invalid'
    return page(visitor,urllib.parse.urljoin(fixture['checkout_url'],form['attrs'].get('action','')),fields)
update_totals('delivery',valid_nonce=False)
assert page(visitor,shop).selected()=='collection'
print('PASS invalid native checkout nonce cannot overwrite the saved preference')
for method in ('delivery','collection'):
    assert update_totals(method).checkout_choice()==method
    assert page(visitor,shop).selected()==method
    assert page(visitor,fixture['checkout_url']).checkout_choice()==method
    print('PASS native no-JavaScript Update Totals persists '+method+' back to menu and checkout')
assert wp('echo wc_get_orders(array("limit"=>1,"paginate"=>true))->total;').strip()==order_count
print('PASS native Update Totals creates no orders')
print('FULFILMENT HTTP complete: real theme, native forms and AJAX fragments, no order submitted')
