<?php

namespace App\Enums;

enum ClientStatus: string
{
    case New = 'NEW';
    case LinkSent = 'LINK_SENT';
    case LinkOpened = 'LINK_OPENED';
    case OauthConnected = 'OAUTH_CONNECTED';
    case ProfileSelected = 'PROFILE_SELECTED';
    case RequestSubmitted = 'REQUEST_SUBMITTED';
    case PendingApproval = 'PENDING_APPROVAL';
    case Connected = 'CONNECTED';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
    case Rejected = 'REJECTED';
    case Revoked = 'REVOKED';

    /**
     * The state machine: a status may only move to one of the listed statuses.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::LinkSent, self::RequestSubmitted, self::Revoked],
            self::LinkSent => [self::LinkOpened, self::RequestSubmitted, self::Expired, self::Revoked],
            self::LinkOpened => [self::LinkSent, self::OauthConnected, self::RequestSubmitted, self::Failed, self::Expired, self::Revoked],
            self::OauthConnected => [self::LinkSent, self::ProfileSelected, self::Failed, self::Expired, self::Revoked],
            self::ProfileSelected => [self::LinkSent, self::OauthConnected, self::RequestSubmitted, self::Failed, self::Expired, self::Revoked],
            self::RequestSubmitted => [self::PendingApproval, self::Connected, self::Failed, self::Rejected, self::Revoked],
            self::PendingApproval => [self::Connected, self::Failed, self::Rejected, self::Revoked, self::LinkSent],
            self::Connected => [self::Revoked, self::Rejected],
            self::Failed => [self::LinkSent, self::OauthConnected, self::ProfileSelected, self::RequestSubmitted, self::PendingApproval, self::Connected, self::Revoked],
            self::Expired => [self::LinkSent, self::Revoked],
            self::Rejected => [self::LinkSent, self::RequestSubmitted, self::Connected, self::Revoked],
            self::Revoked => [self::LinkSent, self::New],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return $this === $next || in_array($next, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::LinkSent => 'Link sent',
            self::LinkOpened => 'Link opened',
            self::OauthConnected => 'Google connected',
            self::ProfileSelected => 'Profile selected',
            self::RequestSubmitted => 'Request submitted',
            self::PendingApproval => 'Awaiting approval',
            self::Connected => 'Connected',
            self::Failed => 'Failed',
            self::Expired => 'Link expired',
            self::Rejected => 'Rejected',
            self::Revoked => 'Revoked',
        };
    }

    /** Tone used by the status badge component. */
    public function tone(): string
    {
        return match ($this) {
            self::Connected => 'green',
            self::Failed, self::Rejected => 'red',
            self::PendingApproval, self::RequestSubmitted => 'amber',
            self::Expired, self::Revoked => 'slate',
            self::LinkOpened, self::OauthConnected, self::ProfileSelected => 'blue',
            default => 'plain',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::RequestSubmitted, self::PendingApproval], true);
    }
}
