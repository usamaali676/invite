<?php

namespace App\Enums;

enum AccessRequestStatus: string
{
    case Submitted = 'SUBMITTED';
    case PendingApproval = 'PENDING_APPROVAL';
    case Connected = 'CONNECTED';
    case Failed = 'FAILED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';

    /** Statuses that block a second request for the same location. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::PendingApproval, self::Connected], true);
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Submitted, self::PendingApproval], true);
    }
}
