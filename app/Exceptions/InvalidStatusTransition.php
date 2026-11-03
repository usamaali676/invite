<?php

namespace App\Exceptions;

use App\Enums\ClientStatus;
use RuntimeException;

class InvalidStatusTransition extends RuntimeException
{
    public function __construct(public readonly ClientStatus $from, public readonly ClientStatus $to)
    {
        parent::__construct("A client cannot move from {$from->value} to {$to->value}.");
    }
}
