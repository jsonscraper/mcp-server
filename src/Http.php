<?php
declare(strict_types=1);
namespace JsonScraper\Mcp;
final class HttpResponse {
 public function __construct(public int $status=200,public array $headers=[],public string $body=''){}
 public static function json(array $value,int $status=200,array $headers=[]): self {return new self($status,$headers+['Content-Type'=>'application/json; charset=utf-8','Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff'],json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
 public function emit(): never {http_response_code($this->status);foreach($this->headers as $key=>$value){if(is_array($value)){foreach($value as $item)header($key.': '.$item,false);}else header($key.': '.$value);}echo $this->body;exit;}
}
final class OAuthFailure extends \RuntimeException {public function __construct(public string $error,string $description,public int $status=400){parent::__construct($description);}}
