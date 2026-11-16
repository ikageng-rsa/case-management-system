<?php

declare(strict_types=1);

namespace App\Services\Billing;

use RuntimeException;

/* A payment that cannot be recorded or reversed as asked, with the reason to show. */
class PaymentRefused extends RuntimeException {}
