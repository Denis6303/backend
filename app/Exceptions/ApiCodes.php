<?php

namespace App\Exceptions;

use MarcinOrlowski\ResponseBuilder\BaseApiCodes;

class ApiCodes extends BaseApiCodes
{
    public const OK = BaseApiCodes::OK;

    /**
     * API errors (custom).
     */
    public const VALIDATION_EXCEPTION = 1001;
    public const NOT_FOUND = 1002;
    public const FORBIDDEN = 1003;
    public const UNAUTHORIZED = 1004;
}

