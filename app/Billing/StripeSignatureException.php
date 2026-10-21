<?php

declare(strict_types=1);

namespace App\Billing;

use RuntimeException;

final class StripeSignatureException extends RuntimeException
{
    public const MISSING = 'missing';

    public const FORGED = 'forged';

    public const EXPIRED = 'expired';

    public const MALFORMED = 'malformed';

    public const UNCONFIGURED = 'unconfigured';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
