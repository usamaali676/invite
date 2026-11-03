<?php

namespace App\Exceptions;

use App\Enums\GoogleErrorType;
use RuntimeException;

class GoogleApiException extends RuntimeException
{
    public function __construct(
        public readonly GoogleErrorType $type,
        public readonly ?int $httpStatus = null,
        public readonly ?string $googleStatus = null,
        string $detail = '',
    ) {
        parent::__construct($detail !== '' ? $detail : $type->adminMessage());
    }

    public function adminMessage(): string
    {
        return $this->type->adminMessage();
    }

    public function clientMessage(): string
    {
        return $this->type->clientMessage();
    }
}
