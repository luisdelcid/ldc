<?php

declare (strict_types=1);
namespace LDC\Deps\GuzzleHttp\Exception;

/**
 * Exception thrown when connection establishment exceeds the time limit.
 */
class ConnectTimeoutException extends ConnectException
{
}
