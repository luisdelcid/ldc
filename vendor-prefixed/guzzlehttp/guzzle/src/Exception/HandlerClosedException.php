<?php

declare (strict_types=1);
namespace LDC\Deps\GuzzleHttp\Exception;

/**
 * Exception thrown when a handler is closed before a transfer completes.
 */
class HandlerClosedException extends TransferException
{
}
