<?php

namespace App\Enums;

enum VerificationState: string
{
    /** Google confirms the Location Group holds the role on the location. */
    case Connected = 'CONNECTED';

    /** Google shows the invitation exists but nobody has accepted it yet. */
    case Pending = 'PENDING';

    /** Google was reachable and shows neither an admin nor a pending invitation. */
    case Missing = 'MISSING';

    /** The check could not be completed (no valid authorization, API error, ...). */
    case Unverifiable = 'UNVERIFIABLE';
}
