<?php

namespace App\Enums;

enum ActorType: string
{
    case Admin = 'ADMIN';
    case Client = 'CLIENT';
    case System = 'SYSTEM';
}
