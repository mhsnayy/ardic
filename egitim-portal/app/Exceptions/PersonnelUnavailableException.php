<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PersonnelUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Personel Sistemi erişilemiyor.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
