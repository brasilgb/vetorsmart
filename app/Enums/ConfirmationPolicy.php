<?php

namespace App\Enums;

enum ConfirmationPolicy: string
{
    case None = 'none';
    case Manual = 'manual';
    case CustomerConfirmation = 'customer_confirmation';
}
