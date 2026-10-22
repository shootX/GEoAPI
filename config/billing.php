<?php

declare(strict_types=1);

return [
    /*
    | Billing V2 is inert until BILLING_V2=true. Merging this code does not
    | change live prices, quotas, or project limits.
    */
    'v2' => filter_var(env('BILLING_V2', false), FILTER_VALIDATE_BOOL),

    /*
    | Business is 240 requests/minute. That rate stays off until a load test
    | above the measured ~13 requests/second ceiling. While this is false,
    | Business cannot be purchased and an admin-assigned Business workspace
    | is capped at 120/minute.
    */
    'v2_business_rpm' => filter_var(env('BILLING_V2_BUSINESS_RPM', false), FILTER_VALIDATE_BOOL),
];
