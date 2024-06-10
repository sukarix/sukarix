<?php

declare(strict_types=1);

namespace Sukarix\Enum;

use MabeEnum\Enum;

class UserRole extends Enum
{
    public const VISITOR  = 'visitor';
    public const ADMIN    = 'admin';
    public const CUSTOMER = 'customer';
    public const API      = 'api';
}
