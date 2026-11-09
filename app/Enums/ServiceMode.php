<?php

namespace App\Enums;

enum ServiceMode: string
{
    case InPerson = 'in_person';
    case Remote = 'remote';
    case CustomerLocation = 'customer_location';
}
