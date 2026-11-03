<?php

namespace App\Enums;

enum ConnectionStatus: string
{
    case Active = 'ACTIVE';
    case Revoked = 'REVOKED';
    case Expired = 'EXPIRED';
}
