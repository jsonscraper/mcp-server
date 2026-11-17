<?php
declare(strict_types=1);
namespace JsonScraper\Mcp;
final class ApiRequestFailure extends \RuntimeException {
 public function __construct(public readonly int $status,public readonly int $attempts,public readonly bool $retryable){parent::__construct('API read failed.');}
}
