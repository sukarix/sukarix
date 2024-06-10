<?php

declare(strict_types=1);

namespace Sukarix\Enum;

use MabeEnum\Enum;

class ResponseCode extends Enum
{
    public const HTTP_OK                   = 200;
    public const HTTP_NO_CONTENT           = 204;
    public const HTTP_BAD_REQUEST          = 400;
    public const HTTP_UNAUTHORIZED         = 401;
    public const HTTP_FORBIDDEN            = 403;
    public const HTTP_NOT_FOUND            = 404;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    // RFC4918
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_NOT_MODIFIED          = 304;
    public const HTTP_PRECONDITION_FAILED   = 412;
}
