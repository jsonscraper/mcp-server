<?php
declare(strict_types=1);
// Works both as a standalone checkout and as a dependency installed by Composer.
$composerAutoload=$GLOBALS['_composer_autoload_path']??null;
if(!is_string($composerAutoload)||!is_file($composerAutoload)){
 $composerAutoload=null;
 foreach([dirname(__DIR__).'/vendor/autoload.php',dirname(__DIR__,3).'/autoload.php'] as $candidate){if(is_file($candidate)){$composerAutoload=$candidate;break;}}
}
if($composerAutoload===null)throw new RuntimeException('Install dependencies with Composer before running the MCP server.');
require_once $composerAutoload;
