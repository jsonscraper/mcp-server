<?php
declare(strict_types=1);
$root=dirname(__DIR__);$adapter=tempnam(sys_get_temp_dir(),'mcp-cli-validator-');
file_put_contents($adapter,'<?php return static fn(string $key):bool=>$key==="fixture_license_12345";');chmod($adapter,0600);
try{
 foreach([['instagram'],['tiktok'],['instagram','tiktok']] as $products){
  $env=getenv();unset($env['JSONSCRAPER_INSTAGRAM_LICENSE_KEY'],$env['JSONSCRAPER_TIKTOK_LICENSE_KEY']);$env['JSONSCRAPER_MCP_LICENSE_VALIDATOR']=$adapter;
  foreach($products as $product)$env['JSONSCRAPER_'.strtoupper($product).'_LICENSE_KEY']='fixture_license_12345';
  $p=proc_open([PHP_BINARY,$root.'/bin/jsonscraper-mcp'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,$env);
  fwrite($pipes[0],json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/list'])."\n");fclose($pipes[0]);$reply=json_decode(stream_get_contents($pipes[1]),true);fclose($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($p);
  $expected=1+(in_array('instagram',$products,true)?94:0)+(in_array('tiktok',$products,true)?54:0);
  if($exit!==0||$stderr!==''||count($reply['result']['tools']??[])!==$expected)throw new RuntimeException('CLI contract failed for '.implode('+',$products));
 }
 echo "CLI transport: Instagram-only, TikTok-only and dual-license catalogues passed.\n";
}finally{unlink($adapter);}
