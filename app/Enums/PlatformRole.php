<?php

namespace App\Enums;

enum PlatformRole: string
{
    case Member = 'member';
    case Moderator = 'moderator';
    case SuperAdmin = 'super_admin';
}
