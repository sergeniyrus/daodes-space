<?php
namespace App\Exceptions;
use RuntimeException;
class FirebaseMessagingException extends RuntimeException
{
    public function __construct(string $message, private readonly bool $unregisteredToken = false)
    {
        parent::__construct($message);
    }
    public function isUnregisteredToken(): bool
    {
        return $this->unregisteredToken;
    }
}
