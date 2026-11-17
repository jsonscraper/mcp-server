<?php
declare(strict_types=1);
namespace JsonScraper\Mcp;
/** Product-specific schemas keep assistant requests bounded and unambiguous. */
final class UptimeScoutProtocol {
 private const PARAMETERS=['services'=>['q','category','status','region','lang','limit','offset'],'status'=>['service','region','lang'],'statuses'=>['services','region','lang'],'summary'=>['region','lang','services','q','category'],'history'=>['service','region','lang','hours','limit','offset'],'stats'=>['service','region','lang','hours'],'incidents'=>['region','lang','services','q','category','limit','offset']];
 public function __construct(private \Closure $fetch){}
 public function tools():array{
  $props=[
   'service'=>['type'=>'string','minLength'=>1,'maxLength'=>100,'pattern'=>'^[\\p{L}\\p{N}_-]+$','description'=>'Exact service ID returned by uptimescout_services, for example github.'],
   'services'=>['type'=>'string','minLength'=>1,'maxLength'=>2000,'description'=>'Comma-separated service IDs from the catalog, for example github,cloudflare. Batch maximum depends on the license.'],
   'region'=>['type'=>'string','pattern'=>'^[A-Za-z]{2}$','description'=>'Collected observation country as an ISO code, for example GB, DE or JP. Read available_regions in the response. Specify a region when comparing services. Omit only when a combined view is intended.'],
   'lang'=>['type'=>'string','enum'=>['en','ar','cs','de','es','fi','fr','he','it','ja','ko','nl','no','pl','pt','ru','sv','tr','uk','zh'],'description'=>'Language of localized service descriptions. Independent of observation country. Responses include a cacheable own-domain logo_url and description_language.'],
   'q'=>['type'=>'string','maxLength'=>80,'description'=>'Search service name or ID.'],
   'category'=>['type'=>'string','maxLength'=>100,'description'=>'Exact category value returned by the service catalog.'],
   'status'=>['type'=>'string','enum'=>['operational','degraded','outage','unknown'],'description'=>'Filter normalized signals; unknown also covers stale or unavailable observations.'],
   'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>200,'description'=>'Page size. Use next_offset to continue; do not request repeated first pages.'],
   'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000,'description'=>'Pagination offset returned as next_offset.'],
   'hours'=>['type'=>'integer','minimum'=>1,'maximum'=>2160,'description'=>'Lookback window in hours. License history depth also applies; 24 means the past day.']];
  $descriptions=['services'=>'Search the catalog to discover exact service IDs and categories','status'=>'Read the latest saved regional observation for one service','statuses'=>'Read several service statuses in one quota-counted batch request','summary'=>'Summarize current regional signals; services optionally restricts the summary to selected IDs','history'=>'Read saved observations over a lookback window; history may be paginated and gaps remain unknown','stats'=>'Summarize all available samples in the requested window; operational_sample_percent is a sample ratio, never measured uptime','incidents'=>'List fresh elevated disruption signals; an empty result does not prove measured uptime or confirmed absence of incidents'];
  $tools=[];foreach(self::PARAMETERS as $method=>$keys){$required=in_array($method,['status','history','stats'],true)?['service']:($method==='statuses'?['services']:[]);$tools[]=['name'=>'uptimescout_'.$method,'title'=>'UptimeScout · '.$method,'description'=>$descriptions[$method].'. Uses the UptimeScout license and its normal quota. Read observed_at, stale and region before describing availability. Missing or stale data is unknown.','inputSchema'=>['type'=>'object','properties'=>array_intersect_key($props,array_flip($keys)),'required'=>$required,'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>true],'securitySchemes'=>[['type'=>'oauth2','scopes'=>['uptimescout:read']]]];}return $tools;
 }
 public function dispatch(array $request,string $license):?array{
  if(!array_key_exists('id',$request))return null;$id=$request['id'];$method=$request['method']??'';
  if($method==='tools/list')return ['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>$this->tools()]];
  if(in_array($method,['resources/list','prompts/list'],true))return ['jsonrpc'=>'2.0','id'=>$id,'result'=>[$method==='resources/list'?'resources':'prompts'=>[]]];
  if($method!=='tools/call')return ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32601,'message'=>'Unknown method']];
  $params=$request['params']??[];$tool=null;foreach($this->tools() as $candidate)if($candidate['name']===($params['name']??''))$tool=$candidate;$args=$params['arguments']??[];
  $invalid=static fn()=>['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32602,'message'=>'Invalid UptimeScout arguments. Use the selected tool schema and catalog service IDs.']];
  if(!$tool||!is_array($args)||array_diff(array_keys($args),array_keys($tool['inputSchema']['properties']))||array_diff($tool['inputSchema']['required'],array_keys($args)))return $invalid();
  foreach($args as $key=>$value){$spec=$tool['inputSchema']['properties'][$key];if($spec['type']==='integer'){if(!is_int($value)||$value<$spec['minimum']||$value>$spec['maximum'])return $invalid();}else{if(!is_string($value)||mb_strlen($value)<($spec['minLength']??0)||mb_strlen($value)>($spec['maxLength']??2000))return $invalid();if(isset($spec['pattern'])&&!preg_match('~'.$spec['pattern'].'~uD',$value))return $invalid();if(isset($spec['enum'])&&!in_array($value,$spec['enum'],true))return $invalid();}}
  if(isset($args['services'])){foreach(explode(',',$args['services']) as $service)if(!preg_match('/^[\p{L}\p{N}_-]{1,100}$/uD',trim($service)))return $invalid();if(count(explode(',',$args['services']))>100)return $invalid();$args['services']=implode(',',array_map('trim',explode(',',$args['services'])));}
  try{$data=($this->fetch)('/'.substr($tool['name'],12),$args,$license);$result=['isError'=>($data['status']??'')!=='ok','content'=>[['type'=>'text','text'=>json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]],'structuredContent'=>$data];}
  catch(ApiRequestFailure $e){$result=['isError'=>true,'content'=>[['type'=>'text','text'=>'UptimeScout request could not be completed. Check the connection, license and quota; do not infer a fresh status from this error.']],'structuredContent'=>['status'=>'fail','error_code'=>'request_unavailable','http_status'=>$e->status,'retryable'=>$e->retryable]];}
  catch(\Throwable){$result=['isError'=>true,'content'=>[['type'=>'text','text'=>'UptimeScout is unavailable. No fresh status can be inferred.']]];}
  return ['jsonrpc'=>'2.0','id'=>$id,'result'=>$result];
 }
}
