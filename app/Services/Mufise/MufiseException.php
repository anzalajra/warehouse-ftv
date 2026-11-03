<?php

namespace App\Services\Mufise;

use RuntimeException;

class MufiseException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly int $retryAfter = 5,
        public readonly ?string $reason = null,
        public readonly ?string $action = null,
        public readonly ?string $requestId = null,
        public readonly bool $retryable = false,
        public readonly ?string $warehouseRequestId = null,
    ) {
        parent::__construct($message);
    }

    public function problem(): array
    {
        return [
            'code' => $this->errorCode, 'message' => $this->getMessage(),
            'reason' => $this->reason ?? $this->errorCode, 'action' => $this->action,
            'request_id' => $this->requestId, 'warehouse_request_id' => $this->warehouseRequestId,
            'retryable' => $this->retryable, 'retry_after' => $this->retryable ? $this->retryAfter : null,
        ];
    }
}
