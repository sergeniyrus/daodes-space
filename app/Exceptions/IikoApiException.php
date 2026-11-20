<?php

namespace App\Exceptions;

use RuntimeException;

class IikoApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly array|string|null $responseBody = null
    ) {
        parent::__construct($message);
    }
}
