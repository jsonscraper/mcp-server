<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use JsonScraper\Mcp\{MediaProxy,Factory};
$checks=0;
function checkMedia(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$sources=[
 'https://scontent-a.cdninstagram.com/avatar.jpg?sig=a%2Fb%2Bc&x=1&x=2',
 'https://video.fbcdn.net/video.mp4?token=abc+def%3D%3D',
 'https://p16-sign.tiktokcdn.com/cover.webp?x-signature=a%2Bb&x-expires=1800000000',
 'https://v16-webapp-prime.us.tiktok.com/video.mp4?secret=a%2Fb',
 'https://sf16-ies-music.tiktokcdn.com/audio.mp3',
 'https://v16m-default.akamaized.net/video.mp4',
];
foreach($sources as $url){$proxy=MediaProxy::url($url);checkMedia(str_starts_with($proxy,MediaProxy::ORIGIN.'/?url='),'Media host proxied');parse_str(parse_url($proxy,PHP_URL_QUERY),$query);checkMedia($query['url']===$url,'Signed source preserved exactly');checkMedia(MediaProxy::url($proxy)===$proxy,'Proxy is idempotent');}
foreach($sources as $url){$download=MediaProxy::downloadUrl($url);checkMedia($download===MediaProxy::url($url).'&download=1','Download uses outer proxy parameter');checkMedia(MediaProxy::downloadUrl($download)===$download,'Download URL is idempotent');parse_str(parse_url($download,PHP_URL_QUERY),$query);checkMedia($query['url']===$url,'Download preserves complete signed source');}
foreach(['https://evil.test/video.mp4','https://cors.jsonscraper.com/?url='.rawurlencode('https://127.0.0.1/a.mp4'),'caption https://p16.tiktokcdn.com/a.mp4'] as $url)checkMedia(MediaProxy::downloadUrl($url)===null,'Unsafe or non-media URLs have no download');
foreach(['https://www.instagram.com/apple/','https://www.tiktok.com/@therock','https://jsonscraper.com/mcp','https://cdninstagram.com.evil.test/a.jpg','https://user:pass@p16.tiktokcdn.com/a.jpg','https://p16.tiktokcdn.com:123/a.jpg','https://127.0.0.1/a.jpg','A caption mentioning https://p16.tiktokcdn.com/a.jpg'] as $url)checkMedia(MediaProxy::url($url)===$url,'Non-media or unsafe URLs unchanged');
$fixture=[
 'status'=>'ok',
 'ig'=>[
  'username'=>'fixture','profile_pic_url'=>$sources[0],
  'items'=>[[
   'image_versions2'=>['candidates'=>[['url'=>$sources[0]]]],
   'video_versions'=>[['url'=>$sources[1]]],
   'cover'=>$sources[2],
   'music'=>['play_url'=>['url_list'=>[$sources[4],$sources[4]]]],
  ]],
 ],
 'tiktok'=>[
  'user'=>['unique_id'=>'fixture','avatar_medium'=>['url_list'=>[$sources[2]]]],
  'aweme_list'=>[[
   'video'=>['play_addr'=>['url_list'=>[$sources[3],$sources[5]]]],
   'music'=>['play_url'=>['url_list'=>[$sources[4]]]],
  ]],
 ],
 'id'=>'9223372036854775807999','count'=>0,'missing'=>null,'is_private'=>false,
 'caption'=>'Caption with https://p16.tiktokcdn.com/a.jpg',
 'profile_url'=>'https://www.instagram.com/fixture/',
];
$calls=0;$p=Factory::protocol(static function($product,$path,$args,$license)use(&$calls,$fixture){$calls++;return $fixture;});$credentials=json_encode(['instagram'=>'fixture_instagram','tiktok'=>'fixture_tiktok']);
$expected=MediaProxy::response($fixture);$dataTools=0;
checkMedia(count($expected['media_downloads'])===count($sources),'Download list deduplicates nested images, video and audio');
foreach($expected['media_downloads'] as $index=>$entry){checkMedia(array_keys($entry)===['url','mcp_download_url'],'Download field has the requested MCP prefix');checkMedia($entry['mcp_download_url']===$entry['url'].'&download=1','Each entry pairs preview and attachment links');}
checkMedia(MediaProxy::response($expected)===$expected,'Response transformation is idempotent');
checkMedia(MediaProxy::response(['count'=>0,'items'=>[]])===['count'=>0,'items'=>[]],'No download field for non-media data');
checkMedia(MediaProxy::response([$sources[0]])===MediaProxy::response(['data'=>[$sources[0]]]),'List responses use the same object envelope');
$init=$p->dispatch(['jsonrpc'=>'2.0','id'=>0,'method'=>'initialize','params'=>[]],$credentials);
checkMedia(str_contains($init['result']['instructions'],'mcp_download_url'),'Server instructs models to use download links');
foreach($p->tools() as $tool){
 if(!str_starts_with($tool['name'],'instagram_')&&!str_starts_with($tool['name'],'tiktok_'))continue;
 checkMedia(str_contains($tool['description'],'mcp_download_url'),'Each data tool documents its download links');
 $arguments=[];foreach($tool['inputSchema']['required']??[] as $name)$arguments[$name]='fixture';
 $reply=$p->dispatch(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>$tool['name'],'arguments'=>$arguments]],$credentials);
 checkMedia(empty($reply['result']['isError']),'Fixture tool succeeds: '.$tool['name']);
 checkMedia($reply['result']['structuredContent']===$expected,'All nested media proxied: '.$tool['name']);
 checkMedia(json_decode($reply['result']['content'][0]['text'],true,128,JSON_THROW_ON_ERROR)===$expected,'Text JSON matches structured result: '.$tool['name']);$dataTools++;
}
checkMedia($dataTools===148&&$calls===148,'Every data tool tested; rewriting never makes another API call');
$error=['isError'=>true,'content'=>[['type'=>'text','text'=>'Upstream error.']]];checkMedia(MediaProxy::result($error)===$error,'Plain errors remain unchanged');
echo "Media links: $checks checks passed across all $dataTools data tools, nested media, signatures, JSON parity and unchanged non-media fields.\n";
