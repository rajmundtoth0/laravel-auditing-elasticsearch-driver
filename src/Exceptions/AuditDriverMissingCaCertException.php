<?php

namespace rajmundtoth0\AuditDriver\Exceptions;

use Exception;
use Throwable;

class AuditDriverMissingCaCertException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
