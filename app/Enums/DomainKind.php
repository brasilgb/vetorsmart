<?php

namespace App\Enums;

enum DomainKind: string
{
    case Subdomain = 'subdomain';
    case Custom = 'custom';
}
