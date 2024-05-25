<?php

namespace rajmundtoth0\AuditDriver\Exceptions;

use Exception;
use Throwable;

class AuditDriverConfigNotSetException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
