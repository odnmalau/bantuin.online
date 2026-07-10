<?php

namespace App;

enum TeamMembershipRole: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Collaborator = 'collaborator';
}
