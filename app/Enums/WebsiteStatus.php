<?php

namespace App\Enums;

enum WebsiteStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';
}
