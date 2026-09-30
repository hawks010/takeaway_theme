<?php
// Isolated test doubles. This process never connects to WordPress, customers or providers.
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600); define('DAY_IN_SECONDS', 86400);
$GLOBALS['options'] = []; $GLOBALS['notices'] = []; $GLOBALS['meta'] = []; $GLOBALS['users'] = []; $GLOBALS['orders'] = []; $GLOBALS['queries'] = []; $GLOBALS['events'] = []; $GLOBALS['mails'] = [];
function get_option($k, $d = false) { return $GLOBALS['options'][$k] ?? $d; }
function update_option($k, $v, $autoload = null) { $GLOBALS['options'][$k]=$v; return true; }
function delete_option($k) { unset($GLOBALS['options'][$k]); return true; }
function get_bloginfo($k = '') { return 'Fixture shop'; }
function wp_parse_args($v,$d=[]) { return array_merge($d,(array)$v); }
function __($v,$domain='') { return $v; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s)); }
function sanitize_text_field($s) { return trim(strip_tags((string)$s)); }
function sanitize_textarea_field($s) { return sanitize_text_field($s); }
function sanitize_email($s) { return filter_var($s,FILTER_SANITIZE_EMAIL); }
function is_email($s) { return filter_var($s,FILTER_VALIDATE_EMAIL); }
function wp_unslash($v) { return $v; }
function absint($n) { return abs((int)$n); }
function wp_timezone() { return new DateTimeZone(get_option('timezone_string','Europe/London')); }
function current_time($type, $gmt=false) { return time()+($gmt?0:3600); }
function wp_date($fmt,$ts=null,$tz=null) { return (new DateTimeImmutable('@'.($ts??time())))->setTimezone($tz??wp_timezone())->format($fmt); }
function date_i18n($fmt,$ts=null) { return wp_date($fmt,$ts); }
function wc_add_notice($s,$type='') { $GLOBALS['notices'][]=$s; }
function get_user_meta($id,$k,$single=true) { return $GLOBALS['meta'][$id][$k]??''; }
function update_user_meta($id,$k,$v) { $GLOBALS['meta'][$id][$k]=$v; return true; }
function delete_user_meta($id,$k) { unset($GLOBALS['meta'][$id][$k]); }
function get_userdata($id) { return $GLOBALS['users'][$id]??false; }
function get_user_by($by,$v) { foreach($GLOBALS['users'] as $u) if(strtolower($u->user_email)===strtolower($v)) return $u; return false; }
function get_users($args=[]) { $out=[]; foreach($GLOBALS['users'] as $id=>$u) if(get_user_meta($id,$args['meta_key']??'')===($args['meta_value']??'')) $out[]=($args['fields']??'')==='ID'?$id:$u; return $out; }
function wp_clear_scheduled_hook($hook,$args=[]) { unset($GLOBALS['events'][$hook]); return 1; }
function wp_next_scheduled($hook) { return $GLOBALS['events'][$hook]['time']??false; }
function wp_schedule_event($time,$freq,$hook) { $GLOBALS['events'][$hook]=compact('time','freq'); return true; }
function wp_schedule_single_event($time,$hook,$args=[]) { $GLOBALS['events'][$hook]=compact('time','args'); return true; }
function wp_mail($to,$subject,$body,$headers=[]) { $GLOBALS['mails'][]=compact('to','subject','body'); return true; }
function add_action(...$args) { $GLOBALS['registered_hooks'][]=$args; }
function add_filter(...$args) { $GLOBALS['registered_hooks'][]=$args; }
function add_shortcode(...$args) {}
function current_user_can($cap) { return $GLOBALS['caps'][$cap] ?? ($GLOBALS['permission']??true); }
function register_rest_route($ns,$path,$config) { $GLOBALS['routes'][$ns.$path]=$config; }
function esc_url_raw($s) { return $s; } function esc_html($s) { return htmlspecialchars((string)$s); }
function apply_filters($name,$value,...$args) { return $value; }
function get_woocommerce_currency() { return get_option('woocommerce_currency','GBP'); }
function wc_get_price_decimals() { return 2; }
function wc_add_number_precision($n,$round=true) { return round((float)$n*100); }
function wc_remove_number_precision($n) { return $n/100; }
function wc_format_decimal($n,$dp=false) { return number_format((float)$n,$dp===false?2:$dp,'.',''); }
function wc_get_orders($args=[]) {
 $GLOBALS['queries'][]=$args; $rows=$GLOBALS['orders'];
 if(isset($args['date_created']) && strpos($args['date_created'],'...')!==false){[$from,$to]=array_map('intval',explode('...',$args['date_created']));$rows=array_filter($rows,fn($o)=>$o->get_date_created()->getTimestamp()>=$from&&$o->get_date_created()->getTimestamp()<=$to);}
 if(isset($args['status']))$rows=array_filter($rows,fn($o)=>in_array($o->get_status(),$args['status'],true));
 if(isset($args['currency'])) $rows=array_filter($rows,fn($o)=>$o->get_currency()===$args['currency']);
 usort($rows,fn($a,$b)=>($args['order']??'DESC')==='ASC'?$a->id<=>$b->id:$b->id<=>$a->id);
 $limit=$args['limit']??10; $page=$args['page']??($args['paged']??1); $total=count($rows);
 $rows=$limit===-1?array_values($rows):array_slice($rows,($page-1)*$limit,$limit);
 if(($args['return']??'')==='ids') $rows=array_map(fn($o)=>$o->id,$rows);
 return !empty($args['paginate'])?(object)['orders'=>$rows,'total'=>$total,'max_num_pages'=>(int)ceil($total/$limit)]:$rows;
}
class WP_REST_Request { private $data; function __construct($data=[]) {$this->data=$data;} function get_param($k){return $this->data[$k]??null;} function get_json_params(){return $this->data;} }
class WP_REST_Response { public $data; public $status; function __construct($data,$status=200){$this->data=$data;$this->status=$status;} function header(...$a){} function get_data(){return $this->data;} function get_status(){return $this->status;} }
class WP_Error { public $code; public $message; function __construct($code,$message,$data=null){$this->code=$code;$this->message=$message;} }
function is_wp_error($v){return $v instanceof WP_Error;}
class FixtureDate extends DateTimeImmutable { function date_i18n($fmt){return $this->format($fmt);} function date($fmt){return $this->format($fmt);} }
class FixtureOrder {
 public $data=[]; public $updates=[]; public $saved=0; public $id; public $email; public $total; public $status; public $refund; public $currency; public $paid;
 function __construct($id,$email,$total='10.00',$status='completed',$refund='0.00',$currency='GBP',$paid=true){foreach(compact('id','email','total','status','refund','currency','paid') as $k=>$v)$this->$k=$v;}
 function get_shipping_method(){return 'Delivery';} function get_meta($key){return $this->data[$key]??'';} function update_meta_data($key,$value){$this->data[$key]=$value;} function save(){$this->saved++;return $this->id;} function update_status($value,$note=''){$this->status=$value;$this->updates[]=$value;return $this->save();} function get_id(){return $this->id;} function get_status(){return $this->status;} function get_billing_email(){return $this->email;} function get_formatted_billing_full_name(){return 'Fixture Customer';} function get_billing_phone(){return '';} function get_shipping_postcode(){return '';} function get_billing_postcode(){return '';}
 function get_total_tax(){return 1.25;} function get_total_tax_refunded(){return 0.25;} function get_item_count(){return 0;} function get_discount_total(){return 0;} function get_shipping_total(){return 0;} function get_payment_method_title(){return 'Fixture gateway';} function get_payment_method(){return 'fixture';} function get_date_created(){return new FixtureDate('2026-01-01 12:00:00');} function get_total(){return $this->total;} function get_total_refunded(){return $this->refund;} function get_currency(){return $this->currency;} function get_items(){return [];}
 function is_paid(){return in_array($this->status,['processing','completed'],true)&&$this->paid;} function get_date_paid(){return $this->paid?new FixtureDate('2026-01-01'):null;}
}
$base=dirname(__DIR__,2).'/takeaway-os/includes/';
foreach(['settings','site-content','operations','production','retention','dashboard-rest','admin','oauth-connectors','features','accounting','woocommerce','analytics','onboarding','activator','packages'] as $f) if(is_file($base.'class-'.$f.'.php'))require_once $base.'class-'.$f.'.php';
function invoke_private($class,$name,...$args){$m=new ReflectionMethod($class,$name);$m->setAccessible(true);return $m->invoke(null,...$args);}
function reset_fixture(){ $GLOBALS['options']=[];$GLOBALS['notices']=[];$GLOBALS['orders']=[];$GLOBALS['queries']=[];$GLOBALS['meta']=[];$GLOBALS['users']=[];$GLOBALS['events']=[];$GLOBALS['mails']=[];$_POST=[];$GLOBALS['permission']=true;$GLOBALS['caps']=[];$GLOBALS['registered_hooks']=[];$GLOBALS['actions']=[];$GLOBALS['products']=[];
 $GLOBALS['options']['timezone_string']='Europe/London';
 $days=[];foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $d)$days[$d]=['closed'=>'0','open'=>'00:00','close'=>'23:59'];
 $GLOBALS['options']['ttos_site_content']=['opening_times'=>['days'=>$days,'override'=>'normal','temporary_closure'=>'0']];
}
function esc_url($s){return $s;}
function home_url($s=''){return 'https://fixture.invalid'.$s;}

class WooCommerce {}
function WC(){return (object)['session'=>null];}
function wc_get_order($id){foreach($GLOBALS['orders'] as $o)if($o->id===$id)return $o;return false;}
function do_action($hook,...$args){$GLOBALS['actions'][]=array_merge([$hook],$args);}
function wc_get_order_status_name($status){return $status;}
function wp_die($s){throw new RuntimeException(strip_tags($s));}
function post_type_exists($t){return false;}
function taxonomy_exists($t){return false;}
function wp_unschedule_hook($h){unset($GLOBALS['events'][$h]);return 1;}
function wp_get_scheduled_event($h,$args=[]){return $GLOBALS['events'][$h]??false;}
class TTOS_Page_Manager {static function ensure_all(...$a){} static function sync_woocommerce_page_options(){}}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}
function set_transient($k,$v,$expiry=0){$GLOBALS['transients'][$k]=$v;}
function flush_rewrite_rules(){}
function esc_attr($s){return htmlspecialchars((string)$s);}
function wp_kses_post($s){return (string)$s;}
function wc_get_product($id){return $GLOBALS['products'][$id]??false;}
class WC_Product_Simple {
 public $data=['name'=>'','status'=>'publish','stock_status'=>'instock','manage_stock'=>false,'stock_quantity'=>null,'backorders'=>'no','type'=>'simple','regular_price'=>'0','sale_price'=>'','tax_status'=>'taxable','featured'=>false,'image_id'=>0,'category_ids'=>[]];public $id;
 function __construct($id=0){$this->id=$id;}
 function __call($method,$args){if(strpos($method,'set_')===0){$this->data[substr($method,4)]=$args[0];return;}if(strpos($method,'get_')===0)return $this->data[substr($method,4)]??'';throw new RuntimeException('Unknown product method '.$method);}
 function get_id(){return $this->id;} function is_type($v){return $this->data['type']===$v;}
 function backorders_allowed(){return $this->data['backorders']!=='no';}
 function managing_stock(){return $this->data['manage_stock'];}
 function update_meta_data($k,$v){$this->data[$k]=$v;}
 function save(){if(!$this->id)$this->id=count($GLOBALS['products'])+1;$GLOBALS['products'][$this->id]=$this;return $this->id;}
}

function remove_action(...$args) {}

function is_admin(){return true;}
function wp_doing_ajax(){return false;}
function admin_url($path=''){return 'https://fixture.invalid/wp-admin/'.$path;}
function wp_safe_redirect($url){throw new RuntimeException('Unexpected redirect to '.$url);}
