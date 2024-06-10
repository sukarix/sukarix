<?php

declare(strict_types=1);

namespace Sukarix\Enum;

use MabeEnum\Enum;

class ErrorChannel extends Enum
{
    public const EMAIL = 'email';
    public const ZULIP = 'zulip';
}
