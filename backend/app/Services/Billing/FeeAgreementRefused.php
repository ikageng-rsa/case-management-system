<?php

declare(strict_types=1);

namespace App\Services\Billing;

use RuntimeException;

/* A change to a fee agreement that its state does not allow, with the reason to show. */
class FeeAgreementRefused extends RuntimeException {}
