<?php

namespace LDC\Deps\GuzzleHttp;

use LDC\Deps\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
