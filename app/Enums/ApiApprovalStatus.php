<?php

namespace App\Enums;

enum ApiApprovalStatus: string
{
    case NotRequested = 'NOT_REQUESTED';
    case Requested = 'REQUESTED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::NotRequested => 'Not requested yet',
            self::Requested => 'Requested, waiting for Google',
            self::Approved => 'Approved (quota 300 QPM)',
            self::Rejected => 'Rejected by Google',
        };
    }
}
