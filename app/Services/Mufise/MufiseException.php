<?php

namespace App\Services\Mufise;

use RuntimeException;

class MufiseException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422, public readonly int $retryAfter = 5)
    {
        parent::__construct($message);
    }
}
