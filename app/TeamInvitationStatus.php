<?php

namespace App;

enum TeamInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
