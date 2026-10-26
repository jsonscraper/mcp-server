<?php
declare(strict_types=1);
namespace JsonScraper\Mcp;
interface Store {
 public function get(string $key): ?array;
 public function put(string $key,array $value,int $ttl): void;
 public function take(string $key): ?array;
 public function increment(string $key,int $ttl): int;
}
final class RedisStore implements Store {
 public function __construct(private \Predis\Client $redis,private string $prefix){}
 public function get(string $key): ?array {$value=$this->redis->get($this->prefix.$key);return $value===null?null:json_decode((string)$value,true,32,JSON_THROW_ON_ERROR);}
 public function put(string $key,array $value,int $ttl): void {$this->redis->setex($this->prefix.$key,$ttl,json_encode($value,JSON_THROW_ON_ERROR));}
 public function take(string $key): ?array {$value=$this->redis->executeRaw(['GETDEL',$this->prefix.$key]);return $value===null?null:json_decode((string)$value,true,32,JSON_THROW_ON_ERROR);}
 public function increment(string $key,int $ttl): int {$key=$this->prefix.$key;$count=(int)$this->redis->incr($key);if($count===1)$this->redis->expire($key,$ttl);return $count;}
}
