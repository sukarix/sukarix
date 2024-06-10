<?php

declare(strict_types=1);

namespace Sukarix\Enum;

use MabeEnum\Enum;

class UserStatus extends Enum
{
    public const ACTIVE   = 'active';
    public const INACTIVE = 'inactive';
}
