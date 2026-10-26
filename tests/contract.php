<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use JsonScraper\Mcp\{Store,OAuth,Factory};
final class Memory implements Store {
 public array $data=[];public function get(string $k):?array{return $this->data[$k]??null;}public function put(string $k,array $v,int $ttl):void{$this->data[$k]=$v;}public function take(string $k):?array{$v=$this->get($k);unset($this->data[$k]);return $v;}public function increment(string $k,int $ttl):int{return $this->data[$k]['n']=($this->data[$k]['n']??0)+1;}
}
$n=0;$check=static function(bool $ok,string $message)use(&$n){$n++;if(!$ok)throw new RuntimeException($message);};
$store=new Memory();$verify=static fn($key,$product)=>$key===($product==='instagram'?'ig_valid_key_12345':'tt_valid_key_12345');
$oauth=new OAuth('jsonscraper','','https://mcp.jsonscraper.com',$store,random_bytes(32),$verify);
$client=json_decode($oauth->register(['redirect_uris'=>['https://chatgpt.com/callback']],'127.0.0.1')->body,true);$verifier=str_repeat('a',43);
$query=['client_id'=>$client['client_id'],'redirect_uri'=>'https://chatgpt.com/callback','resource'=>'https://mcp.jsonscraper.com','response_type'=>'code','code_challenge_method'=>'S256','code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'state'=>'kept'];
$fields=static function($response){preg_match('/name="nonce" value="([^"]+)"/',$response->body,$n);preg_match('/name="csrf" value="([^"]+)"/',$response->body,$c);return ['nonce'=>$n[1],'csrf'=>$c[1]];};
$post=static fn($r,$extra)=>$oauth->authorize([],$fields($r)+$extra,['jsonscraper_mcp_jsonscraper'=>$fields($r)['csrf']],'POST','127.0.0.1');
$issue=static function($response)use($oauth,$client,$verifier,$check){parse_str(parse_url($response->headers['Location'],PHP_URL_QUERY),$callback);$check($callback['state']==='kept','state round trip');return json_decode($oauth->token(['grant_type'=>'authorization_code','client_id'=>$client['client_id'],'redirect_uri'=>'https://chatgpt.com/callback','resource'=>'https://mcp.jsonscraper.com','code'=>$callback['code'],'code_verifier'=>$verifier])->body,true);};
foreach(['en','ru','uk','de','fr','es','it','pt','nl','sv','no','fi','pl','cs','tr','ja','ko','zh','ar','he'] as $lang){$r=$oauth->authorize($query,[],[],'GET','locale-'.$lang,$lang);$check(str_contains($r->body,'lang="'.$lang.'"'),'localized form');$check(str_contains($r->body,'name="instagram_license_key"')&&str_contains($r->body,'name="tiktok_license_key"'),'two fields');}
$r=$oauth->authorize($query,[],[],'GET','127.0.0.1');$r=$post($r,['action'=>'check_instagram','instagram_license_key'=>'ig_valid_key_12345']);$check($r->status===200&&str_contains($r->body,'role="status"'),'first license checked');$check(!str_contains($r->body,'ig_valid_key'),'key never echoed');
$r=$post($r,['action'=>'check_tiktok','tiktok_license_key'=>'ig_valid_key_12345']);$check($r->status===400,'wrong product rejected');$r=$post($r,['action'=>'connect']);$ig=$issue($r);$check($ig['scope']==='instagram:read','IG token scope');
$r=$oauth->authorize($query,[],[],'GET','127.0.0.1');$r=$post($r,['action'=>'check_tiktok','tiktok_license_key'=>'tt_valid_key_12345']);$r=$post($r,['action'=>'check_instagram','instagram_license_key'=>'ig_valid_key_12345']);$r=$post($r,['action'=>'connect']);$both=$issue($r);$check($both['scope']==='tiktok:read instagram:read','both scopes');
$calls=[];$protocol=Factory::protocol(static function($product,$path,$args,$key)use(&$calls){$calls[]=[$product,$key];return ['status'=>'ok','source'=>$product];});
$request=static fn($method,$params=[])=>['jsonrpc'=>'2.0','id'=>1,'method'=>$method,'params'=>$params];
$tools=$protocol->dispatch($request('tools/list'),$oauth->license($ig['access_token']))['result']['tools'];$check(count($tools)===95,'94 entitled IG tools plus license status');
$all=$protocol->dispatch($request('tools/list'),$oauth->license($both['access_token']))['result']['tools'];$check(count($all)===149,'148 data tools plus license status');
foreach($all as $tool){$check($tool['annotations']['readOnlyHint']===true&&$tool['annotations']['destructiveHint']===false&&$tool['annotations']['openWorldHint']===($tool['name']!=='jsonscraper_get_license_info'),'accurate annotation');$check(!isset($tool['inputSchema']['properties']->license_key),'no credential tool input');}
$denied=$protocol->dispatch($request('tools/call',['name'=>'tiktok_get_user_info','arguments'=>['query'=>'therock']]),$oauth->license($ig['access_token']));$check(!empty($denied['result']['isError'])&&count($calls)===0,'unauthorized call never reaches API');
$protocol->dispatch($request('tools/call',['name'=>'tiktok_get_user_info','arguments'=>['query'=>'therock']]),$oauth->license($both['access_token']));$check($calls===[['tiktok','tt_valid_key_12345']],'correct product credential routing');
$refresh=$oauth->token(['grant_type'=>'refresh_token','client_id'=>$client['client_id'],'resource'=>'https://mcp.jsonscraper.com','refresh_token'=>$both['refresh_token']]);$check($refresh->status===200,'refresh bundle');try{$oauth->token(['grant_type'=>'refresh_token','client_id'=>$client['client_id'],'resource'=>'https://mcp.jsonscraper.com','refresh_token'=>$both['refresh_token']]);$check(false,'refresh reuse');}catch(JsonScraper\Mcp\OAuthFailure){$check(true,'refresh reuse rejected');}
$oauth->revoke(['token'=>$ig['access_token']]);$check($oauth->license($ig['access_token'])===null,'revocation');
$serialized=json_encode($store->data);$check(!str_contains($serialized,'ig_valid_key')&&!str_contains($serialized,'tt_valid_key'),'credentials encrypted in storage');
$info=new JsonScraper\Mcp\LicenseInfo(static fn($product,$key)=>['status'=>'valid','expiration'=>time()+3*86400,'requests_limit'=>100,'requests_count'=>100,'license_key'=>$key,'customer'=>['email'=>'never-output@example.com']]);
$reply=$info->get(['instagram'=>'never-output-key'],[]);$row=$reply['licenses'][0];$check($row['requests_remaining']===0,'quota exhausted');$check(count($row['alerts'])===2,'expiry and quota warnings');$check($row['license_account_url']==='https://evelode.com/licenses/','account URL without secret');$check(!str_contains(json_encode($reply),'never-output'),'strict metadata whitelist');
$reply=$info->get(['instagram'=>'never-output-key'],['product'=>'tiktok']);$check($reply['licenses'][0]['status']==='not_connected','missing license status');
$unknown=new JsonScraper\Mcp\LicenseInfo(static fn()=>['status'=>'valid']);$check($unknown->get(['tiktok'=>'key'],[])['licenses'][0]['requests_remaining']===null,'unknown balance is not zero');
$unlimited=new JsonScraper\Mcp\LicenseInfo(static fn()=>['status'=>'valid','expiration'=>'lifetime','requests_limit'=>0,'requests_count'=>10]);$row=$unlimited->get(['tiktok'=>'key'],[])['licenses'][0];$check($row['unlimited_requests']&&$row['lifetime']&&$row['requests_remaining']===null,'unlimited and lifetime handled explicitly');
$failed=new JsonScraper\Mcp\LicenseInfo(static fn()=>throw new RuntimeException('never-output-key'));$check(!str_contains(json_encode($failed->get(['tiktok'=>'key'],[])),'never-output'),'backend error redacted');
try{$info->get(['tiktok'=>'key'],['license_key'=>'never-output-key']);$check(false,'extra credential input');}catch(InvalidArgumentException){$check(true,'credential argument rejected');}
echo "OK: $n unified MCP checks\n";
