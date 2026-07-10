<?php

namespace App;

enum UserRole: string
{
    case Admin = 'admin';
    case Candidate = 'candidate';

    public function homePath(): string
    {
        return match ($this) {
            self::Admin => '/admin/rankings',
            self::Candidate => '/candidate/exam',
        };
    }
}
