<?php
declare(strict_types=1);
namespace JsonScraper\Mcp;
final class Server {
 public function __construct(private OAuth $oauth,private Protocol $protocol,private string $origin){}
 public function handle(string $path,string $method,array $headers,array $query,array $post,array $cookies,string $body,string $ip): HttpResponse {
  $headers=array_change_key_case($headers,CASE_LOWER);
  if(isset($headers['origin'])&&rtrim((string)$headers['origin'],'/')!==$this->origin)return HttpResponse::json(['error'=>'Origin is not allowed.'],403);
  if(strlen($body)>262144)return HttpResponse::json(['error'=>'Request is too large.'],413);
  try{
   if($path==='resource-metadata'&&$method==='GET')return $this->oauth->metadata(true);
   if($path==='authorization-metadata'&&$method==='GET')return $this->oauth->metadata(false);
   if($path==='oauth/register'&&$method==='POST')return $this->oauth->register($this->object($body),$ip);
   if($path==='oauth/authorize'&&in_array($method,['GET','POST'],true))return $this->oauth->authorize($query,$post,$cookies,$method,$ip,$headers['accept-language']??'');
   if($path==='oauth/token'&&$method==='POST')return $this->oauth->token($post);
   if($path==='oauth/revoke'&&$method==='POST')return $this->oauth->revoke($post);
   if($path==='health'&&$method==='GET')return HttpResponse::json(['status'=>'ok','transport'=>'streamable-http']);
   if($path!=='server')return HttpResponse::json(['error'=>'Not found.'],404);
   if(!preg_match('/^Bearer ([^\s]+)$/Di',(string)($headers['authorization']??''),$match))return $this->authenticationRequired($method,$headers,$body);
   $license=$this->oauth->license($match[1]);if($license===null)return $this->authenticationRequired($method,$headers,$body);
   if($method!=='POST')return HttpResponse::json(['error'=>'Use JSON-RPC POST; no server-initiated SSE stream.'],405,['Allow'=>'POST']);
   if(!str_starts_with(strtolower((string)($headers['content-type']??'')),'application/json'))return HttpResponse::json(['error'=>'Use application/json.'],415);
   if(isset($headers['mcp-protocol-version'])&&!in_array($headers['mcp-protocol-version'],Protocol::VERSIONS,true))return HttpResponse::json(['error'=>'Unsupported MCP protocol version.'],400);
   $reply=$this->protocol->dispatch($this->object($body),$license);
   return $reply===null?new HttpResponse(202,['Cache-Control'=>'no-store'],''):HttpResponse::json($reply);
  }catch(OAuthFailure $error){return HttpResponse::json(['error'=>$error->error,'error_description'=>$error->getMessage()],$error->status);}
  catch(\JsonException|\InvalidArgumentException){return HttpResponse::json(['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32700,'message'=>'Invalid JSON object.']],400);}
  catch(\Throwable){return HttpResponse::json(['error'=>'Service unavailable. Please retry later.'],503);}
 }
 private function authenticationRequired(string $method,array $headers,string $body): HttpResponse {
  $response=$this->oauth->challenge();
  // Transport discovery keeps the standard HTTP challenge. A cached tool invocation
  // additionally receives ChatGPT's tool-level linking signal, without executing an API call.
  if($method!=='POST'||!str_starts_with(strtolower((string)($headers['content-type']??'')),'application/json'))return $response;
  try{$request=$this->object($body);}catch(\Throwable){return $response;}
  if(($request['jsonrpc']??null)!=='2.0'||($request['method']??null)!=='tools/call'||(!is_string($request['id']??null)&&!is_int($request['id']??null)))return $response;
  $name=$request['params']['name']??null;if(!is_string($name)||!in_array($name,array_column($this->protocol->tools(),'name'),true))return $response;
  $challenge=$response->headers['WWW-Authenticate'].', error="invalid_token", error_description="Connect your jsonscraper API license to continue"';
  return HttpResponse::json(['jsonrpc'=>'2.0','id'=>$request['id'],'result'=>['isError'=>true,'content'=>[['type'=>'text','text'=>'Connect jsonscraper to continue. Enter your API license only on the jsonscraper authorization page, never in chat.']],'_meta'=>['mcp/www_authenticate'=>[$challenge]]]],200);
 }
 private function object(string $body): array {$data=json_decode($body,false,32,JSON_THROW_ON_ERROR);if(!$data instanceof \stdClass)throw new \InvalidArgumentException('Object required.');return json_decode($body,true,32,JSON_THROW_ON_ERROR);}
}
