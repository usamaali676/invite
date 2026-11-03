<?php

namespace App\Services\Google;

use App\Enums\GoogleErrorType;
use App\Enums\VerificationState;

final readonly class VerificationResult
{
    public function __construct(
        public VerificationState $state,
        public string $detail,
        public ?GoogleErrorType $error = null,
        public ?string $locationName = null,
    ) {}

    public function isConnected(): bool
    {
        return $this->state === VerificationState::Connected;
    }
}
