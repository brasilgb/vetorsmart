<?php

namespace App\Enums;

enum TenantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Operator = 'operator';
}
