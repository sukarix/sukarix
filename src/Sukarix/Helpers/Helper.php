<?php

declare(strict_types=1);

namespace Sukarix\Helpers;

use Sukarix\Behaviours\HasEvents;
use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\HasSession;
use Sukarix\Behaviours\LogWriter;
use Sukarix\Core\Tailored;

/**
 * Base Helper Class.
 */
class Helper extends Tailored
{
    use HasEvents;
    use HasF3;
    use HasSession;
    use LogWriter;
}
