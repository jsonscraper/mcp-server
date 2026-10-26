<?php
declare(strict_types=1);
namespace JsonScraper\Mcp;
/** Account metadata only. Never returns credential, customer, payment or license identifiers. */
final class LicenseInfo {
 public const PRODUCTS=['instagram'=>488899,'tiktok'=>132057];
 public function __construct(private ?\Closure $reader=null){}
 public static function definition():array {
  return ['name'=>'jsonscraper_get_license_info','title'=>'Check my licenses and request quota','description'=>'Read the status, expiration and available request quota of the licenses already connected to this jsonscraper connection. With no product filter, checks all connected licenses. Does not consume a social-data API request. Missing values mean unavailable, not zero. No license keys, customer details or payment information are returned.','inputSchema'=>['type'=>'object','properties'=>(object)['product'=>['type'=>'string','enum'=>['instagram','tiktok'],'description'=>'Optional: check only this connected product. Omit to check all connected licenses.']],'additionalProperties'=>false],'securitySchemes'=>[['type'=>'oauth2','scopes'=>[]]],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false]];
 }
 public function get(array $licenses,array $arguments):array {
  foreach($arguments as $key=>$value)if($key!=='product'||!is_string($value)||!isset(self::PRODUCTS[$value]))throw new \InvalidArgumentException('Use an optional product: instagram or tiktok.');
  $selected=isset($arguments['product'])?[$arguments['product']]:array_keys($licenses);$rows=[];
  foreach($selected as $product){
   if(!isset($licenses[$product])){$rows[]=['product'=>$product,'status'=>'not_connected','message'=>'This product license is not connected. Reconnect jsonscraper and add the license on its authorization page. Never send the key in chat.'];continue;}
   try{$raw=$this->reader?($this->reader)($product,$licenses[$product]):self::read($product,$licenses[$product]);
    $status=in_array($raw['status']??'', ['valid','expired','disabled','invalid'],true)?$raw['status']:'unknown';
    $expiration=$raw['expiration']??null;$lifetime=in_array($expiration,['lifetime','never',0,'0'],true);$expires=null;
    if(!$lifetime&&is_scalar($expiration)&&$expiration!==''){$time=is_numeric($expiration)?(int)$expiration:strtotime((string)$expiration.' UTC');if($time&&$time>0)$expires=gmdate('c',$time);}
    if($expires!==null&&strtotime($expires)<=time())$status='expired';
    $limit=isset($raw['requests_limit'])&&is_numeric($raw['requests_limit'])&&$raw['requests_limit']>=0?(int)$raw['requests_limit']:null;
    $used=isset($raw['requests_count'])&&is_numeric($raw['requests_count'])&&$raw['requests_count']>=0?(int)$raw['requests_count']:null;
    $remaining=$limit!==null&&$limit>0&&$used!==null?max(0,$limit-$used):null;
    $alerts=[];if($status==='expired')$alerts[]=['code'=>'license_expired','message'=>'The connected license has expired. Sign in to your existing Evelode account to review this license and available actions.'];
    elseif($expires!==null&&strtotime($expires)-time()<=7*86400)$alerts[]=['code'=>'expires_soon','message'=>'The license expires within seven days. Check the expiration date in your existing account.'];
    if($remaining===0&&$limit>0)$alerts[]=['code'=>'quota_exhausted','message'=>'The request allowance for this license has been used. Further data requests are unavailable under its current entitlement. Sign in to your existing Evelode account to review this license.'];
    elseif($limit>0&&$remaining!==null&&$remaining<=$limit*.1)$alerts[]=['code'=>'quota_low','message'=>'Ten percent or less of the request allowance remains.'];
    $rows[]=['product'=>$product,'status'=>$status,'license_account_url'=>'https://evelode.com/licenses/','expires_at'=>$expires,'lifetime'=>$lifetime,'requests_limit'=>$limit,'requests_used'=>$used,'requests_remaining'=>$remaining,'unlimited_requests'=>$limit===null?null:$limit===0,'quota_period'=>'license_entitlement','quota_source'=>in_array($raw['quota_source']??'', ['api_counter','licensing_service'],true)?$raw['quota_source']:'licensing_service','alerts'=>$alerts];
   }catch(\Throwable){$rows[]=['product'=>$product,'status'=>'unavailable','message'=>'License information is temporarily unavailable. Retry later. No request balance can be inferred.'];}
  }
  return ['licenses'=>$rows,'note'=>'Quota is a current snapshot and can change with concurrent API use. It is not assumed to reset monthly. Null means unavailable. This check does not consume social-data API quota.'];
 }
 public static function read(string $product,string $license,?\Closure $counter=null):array {
  $id=self::PRODUCTS[$product]??throw new \InvalidArgumentException('Unknown product.');
  $reply=self::request('getRemoteLicenseInfo',['download_id'=>$id],$license);$info=$reply['license']??[];
  if(!is_array($info)||(int)($info['download_id']??0)!==$id)return ['status'=>'invalid'];
  $result=['status'=>$reply['license_status']??'unknown','expiration'=>$info['expiration']??null,'quota_source'=>$counter?'api_counter':'licensing_service'];
  if(!isset($info['ID'],$info['price_id']))return $result;
  $query=['license_id'=>(int)$info['ID'],'download_id'=>$id,'price_id'=>(int)$info['price_id']];
  foreach(['getCurrentRequestsLimit'=>'requests_limit','getRequestsCount'=>'requests_count'] as $endpoint=>$field){
   try{$cached=$counter?$counter($field,(int)$info['ID']):null;$value=$cached??(self::request($endpoint,$query,$license)[$field]??null);if(is_numeric($value)&&$value>=0)$result[$field]=(int)$value;}catch(\Throwable){}
  }
  return $result;
 }
 public static function request(string $endpoint,array $query,string $license):array {
  if(!in_array($endpoint,['getRemoteLicenseInfo','getCurrentRequestsLimit','getRequestsCount'],true)||!preg_match('/^[A-Za-z0-9_.-]{10,200}$/D',$license))throw new \InvalidArgumentException('Invalid license request.');
  $c=curl_init('https://evelode.com/graftype-api/'.$endpoint.'/'.rawurlencode($license).'/?'.http_build_query($query,'','&',PHP_QUERY_RFC3986));
  curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: jsonscraper-mcp-license-info/0.3.1'],CURLOPT_NOPROGRESS=>false,CURLOPT_XFERINFOFUNCTION=>static fn($handle,$total,$downloaded)=>$downloaded>262144?1:0]);
  $body=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);if(!is_string($body)||$status!==200)throw new \RuntimeException('License service unavailable.');$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);if(!is_array($data)||($data['status']??'')==='fail')throw new \RuntimeException('License information unavailable.');return $data;
 }
}
