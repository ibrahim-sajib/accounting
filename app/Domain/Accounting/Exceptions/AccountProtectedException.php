<?php

namespace App\Domain\Accounting\Exceptions;

use Exception;

/**
 * Thrown when deleting an account that cannot be removed, e.g. a parent,
 * a system account, or one referenced by tax rates / accounting settings.
 */
class AccountProtectedException extends Exception
{
}