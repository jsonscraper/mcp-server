<?php
declare(strict_types=1);
namespace JsonScraper\Mcp;

/** Apply the media delivery policy to every social-data response, at any depth. */
final class MediaProxy
{
 public const ORIGIN='https://cors.jsonscraper.com';
 public const SUFFIXES=['cdninstagram.com','fbcdn.net','tiktokcdn.com','tiktokcdn-us.com','tiktokcdn-eu.com','tiktokv.com','muscdn.com','byteoversea.com','ibytedtos.com','ibyteimg.com','akamaized.net','tiktokv.us','tiktokcdn-in.com'];
 public static function image(array $candidates):?string
 {
  $fallback=null;
  foreach($candidates as $value){
   if(!is_string($value))continue;$source=$value;
   if(str_starts_with($value,self::ORIGIN.'/?url=')){parse_str((string)parse_url($value,PHP_URL_QUERY),$query);$source=$query['url']??'';if(!is_string($source)||self::url($source)!==$value)continue;}
   elseif(self::url($value)===$value)continue;
   $fallback??=$value;$path=(string)parse_url($source,PHP_URL_PATH);
   if(preg_match('/\.(?:jpe?g|png|webp|avif|gif)(?:$|~)/i',$path))return $value;
  }
  return $fallback;
 }
 public static function url(string $value):string
 {
  // Never decode/rebuild a signed source URL: encode the complete original string once.
  if(!str_starts_with($value,'https://')||strlen($value)>12000)return $value;
  $parts=parse_url($value);if(!is_array($parts)||isset($parts['user'])||isset($parts['pass'])||(isset($parts['port'])&&$parts['port']!==443))return $value;
  $host=strtolower($parts['host']??'');$allowed=$host==='lookaside.instagram.com'||(bool)preg_match('/^v[0-9][a-z0-9-]*(?:\.[a-z0-9-]+)*\.tiktok\.com$/D',$host);
  foreach(self::SUFFIXES as $domain)if($host===$domain||str_ends_with($host,'.'.$domain)){$allowed=true;break;}
  return $allowed?self::ORIGIN.'/?url='.rawurlencode($value):$value;
 }
 public static function data(mixed $value):mixed
 {
  if(is_string($value))return self::url($value);
  if(is_array($value))foreach($value as $key=>$item)$value[$key]=self::data($item);
  return $value;
 }
 public static function downloadUrl(string $value):?string
 {
  $url=self::url($value);$prefix=self::ORIGIN.'/?url=';
  if(!str_starts_with($url,$prefix))return null;
  parse_str((string)parse_url($url,PHP_URL_QUERY),$query);$source=$query['url']??null;
  if(!is_string($source)||self::url($source)===$source)return null;
  // Re-encode the original signed source only; never append to its inner query.
  return self::url($source).'&download=1';
 }
 public static function response(array $data):array
 {
  $data=self::data($data);$downloads=[];
  $walk=static function(mixed $value)use(&$walk,&$downloads):void{
   if(is_array($value)){foreach($value as $key=>$item)if($key!=='media_downloads')$walk($item);return;}
   if(is_string($value)&&($download=self::downloadUrl($value))!==null){$view=substr($download,0,-strlen('&download=1'));$downloads[$view]=['url'=>$view,'mcp_download_url'=>$download];}
  };
  $walk($data);
  if($downloads){if(array_is_list($data))$data=['data'=>$data];$data['media_downloads']=array_values($downloads);}
  return $data;
 }
 public static function result(array $result):array
 {
  if(is_array($result['structuredContent']??null))$result['structuredContent']=self::response($result['structuredContent']);
  foreach($result['content']??[] as $index=>$block){
   if(($block['type']??null)==='text'&&is_string($block['text']??null)){
    try{$data=json_decode($block['text'],true,128,JSON_THROW_ON_ERROR);if(is_array($data))$result['content'][$index]['text']=json_encode(self::response($data),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}catch(\JsonException){}
   }elseif(($block['type']??null)==='resource_link'&&is_string($block['uri']??null))$result['content'][$index]['uri']=self::url($block['uri']);
  }
  return $result;
 }
}
