<?php

namespace App\Enums;

enum AiPayer: string
{
    case User = 'user';
    case Company = 'company';
    case Platform = 'platform';
}
