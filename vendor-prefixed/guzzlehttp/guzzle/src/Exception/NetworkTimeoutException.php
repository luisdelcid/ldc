<?php

declare (strict_types=1);
namespace LDC\Deps\GuzzleHttp\Exception;

/**
 * Exception thrown when a transfer times out before response headers are
 * received.
 */
class NetworkTimeoutException extends NetworkException
{
}
