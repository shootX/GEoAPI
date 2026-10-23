<?php

declare(strict_types=1);

namespace App\Rules;

use Geoapi\Platform\Passwords;
use Illuminate\Validation\Rules\Password;

/**
 * Password::defaults() for every Laravel form (register, reset, change): the rule in
 * Geoapi\Platform\Passwords, with its message in the page language.
 */
final class StrongPassword extends Password
{
    public function __construct()
    {
        parent::__construct(Passwords::MIN);
        $this->max(Passwords::MAX);
    }

    public function passes($attribute, $value)
    {
        $this->messages = [];
        $error = Passwords::error(is_string($value) ? $value : '', app()->getLocale());

        return $error === null ? true : $this->fail($error);
    }
}
