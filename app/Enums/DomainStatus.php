<?php

namespace App\Enums;

enum DomainStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
}
