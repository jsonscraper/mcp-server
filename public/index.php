<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/load.php';
$path=(string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
$route=match($path){'/' =>'server','/.well-known/oauth-protected-resource'=>'resource-metadata','/.well-known/oauth-authorization-server','/.well-known/oauth-authorization-server/oauth','/oauth/.well-known/oauth-authorization-server'=>'authorization-metadata',default=>ltrim($path,'/')};
JsonScraper\Mcp\Factory::create()->handle($route,$_SERVER['REQUEST_METHOD']??'GET',function_exists('getallheaders')?getallheaders():[],$_GET,$_POST,$_COOKIE,(string)file_get_contents('php://input',false,null,0,262145),$_SERVER['REMOTE_ADDR']??'unknown')->emit();
