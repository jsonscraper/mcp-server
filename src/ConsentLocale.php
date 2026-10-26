<?php
declare(strict_types=1);
namespace JsonScraper\Mcp;
/** Localized consent copy. Browser preferences never alter OAuth callback validation. */
final class ConsentLocale {
 public const LANGUAGES=['en','ar','cs','de','es','fi','fr','he','it','ja','ko','nl','no','pl','pt','ru','sv','tr','uk','zh'];
 public static function negotiate(string $header): string {
  $preferences=[];$excluded=[];
  foreach(array_slice(explode(',',substr($header,0,4096)),0,50) as $index=>$part){
   if(!preg_match('/^\s*([A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*|\*)(?:\s*;\s*q=(0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?))?\s*$/D',$part,$m))continue;
   $lang=strtolower(explode('-',$m[1])[0]);$quality=isset($m[2])?(float)$m[2]:1.0;
   if($quality<=0){$excluded[$lang]=true;continue;}
   $preferences[]=['lang'=>$lang,'quality'=>$quality,'index'=>$index];
  }
  usort($preferences,static fn($a,$b)=>($b['quality']<=>$a['quality'])?:($a['index']<=>$b['index']));
  foreach($preferences as $preference){$lang=$preference['lang'];if(in_array($lang,self::LANGUAGES,true)&&!isset($excluded[$lang]))return $lang;}
  return 'en';
 }
 public static function text(string $lang,string $source,array $values=[]): string {
  static $catalogue=null;
  $catalogue??=json_decode((string)file_get_contents(__DIR__.'/consent-translations.json'),true,512,JSON_THROW_ON_ERROR);
  $text=$catalogue[$lang][$source]??$source;
  foreach($values as $key=>$value)$text=str_replace('{'.$key.'}',(string)$value,$text);
  return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 }
}
