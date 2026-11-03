<?php

namespace App\Enums;

enum AccessMethod: string
{
    case Api = 'API';
    case AgencyManual = 'AGENCY_MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::Api => 'Google sign-in (automatic)',
            self::AgencyManual => 'Agency request (manual)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Api => 'Automatic',
            self::AgencyManual => 'Manual',
        };
    }
}
