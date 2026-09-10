<?php

namespace App\Domain\Accounting\Exceptions;

use Exception;

class ClosedPeriodException extends Exception
{
    public function __construct(string $message = 'The accounting period is closed or locked.')
    {
        parent::__construct($message);
    }
}