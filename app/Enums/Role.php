<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'ADMIN';
    case Agent = 'AGENT';
    case Customer = 'CUSTOMER';
}
