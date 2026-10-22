<?php

declare(strict_types=1);

namespace App\Billing;

use RuntimeException;

/** A signed payment callback whose body does not match the order; the whole handling is rolled back. */
final class CallbackMismatch extends RuntimeException {}
