<?php

namespace App\Enums;

enum PlatformRole: string
{
    // Permission role for an ordinary account; never an official GAMAD membership or recognition.
    case Member = 'member';
    case Moderator = 'moderator';
    case SuperAdmin = 'super_admin';
}
