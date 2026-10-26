<?php
declare(strict_types=1);
namespace JsonScraper\Mcp;
final class Factory {
 public static function create():Server {
  $origin=rtrim(getenv('JSONSCRAPER_MCP_ORIGIN')?:'https://mcp.jsonscraper.com','/');
  if(!preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?$~D',$origin))throw new \RuntimeException('Configure a fixed HTTPS public origin.');
  $path=getenv('JSONSCRAPER_MCP_KEY_FILE')?:'/var/lib/jsonscraper-mcp/jsonscraper.key';$key=is_file($path)?file_get_contents($path):false;
  if(!is_string($key)||strlen($key)!==32)throw new \RuntimeException('Configure a private 32-byte OAuth encryption key.');
  $store=new RedisStore(new \Predis\Client(getenv('JSONSCRAPER_MCP_REDIS_URL')?:'tcp://127.0.0.1:6379'),'jsonscraper:mcp:unified:');
  $verify=self::validator();
  return new Server(new OAuth('jsonscraper','',$origin,$store,$key,$verify),self::protocol(),$origin);
 }
 public static function validator():\Closure {
  $adapter=getenv('JSONSCRAPER_MCP_LICENSE_VALIDATOR');
  return static function(string $license,string $product)use($adapter):bool{
   if($adapter){$validator=require $adapter;if(!$validator instanceof \Closure)throw new \RuntimeException('Invalid license validator.');return $validator($license);}
   try{$reply=LicenseInfo::request('getRemoteLicenseInfo',['download_id'=>LicenseInfo::PRODUCTS[$product]],$license);return ($reply['license_status']??'')==='valid'&&(int)($reply['license']['download_id']??0)===LicenseInfo::PRODUCTS[$product];}catch(\Throwable){return false;}
  };
 }
 public static function protocol(?\Closure $fetch=null):Protocol {
  $fetch??=static fn($product,$path,$args,$key)=>self::fetch($product,$path,$args,$key);$protocols=[];
  foreach(['tiktok'=>'Tiktok','instagram'=>'Instagram'] as $product=>$label){$class=__NAMESPACE__.'\\Products\\'.$label.'\\Protocol';$protocols[$product]=new $class($product,$label,json_decode(file_get_contents(__DIR__.'/../docs/'.$product.'-endpoints.json'),true,64,JSON_THROW_ON_ERROR),static fn($path,$args,$key)=>$fetch($product,$path,$args,$key));}
  $infoAdapter=getenv('JSONSCRAPER_MCP_LICENSE_INFO');$reader=$infoAdapter?require $infoAdapter:null;if($reader!==null&&!$reader instanceof \Closure)throw new \RuntimeException('Invalid license info adapter.');return new Protocol($protocols,new LicenseInfo($reader));
 }
 private static function fetch(string $product,string $path,array $args,string $license):array {
  $base=$product==='instagram'?'https://ig.jsonscraper.com':'https://tiktok.evelode.com';$args['license_key']=$license;$curl=curl_init($base.$path.'?'.http_build_query($args,'','&',PHP_QUERY_RFC3986));
  curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>60,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: jsonscraper-mcp/0.3.0'],CURLOPT_NOPROGRESS=>false,CURLOPT_XFERINFOFUNCTION=>static fn($handle,$total,$downloaded)=>$downloaded>2*1024*1024?1:0]);
  $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);if(!is_string($body)||$status<200||$status>=300)throw new \RuntimeException('API request failed.');$data=json_decode($body,true,128,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);if(!is_array($data))throw new \RuntimeException('Expected an API JSON response.');return $data;
 }
}
