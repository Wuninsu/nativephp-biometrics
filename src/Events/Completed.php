<?php

namespace Wuninsu\NativeBiometrics\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sent by the phone when a biometric prompt ends.
 *
 * $error is null on success, otherwise one of: cancelled, failed, lockout, not_enrolled, unavailable, timeout.
 */
class Completed
{
    use Dispatchable;

    public const CANCELLED = 'cancelled';

    public const FAILED = 'failed';

    public const LOCKOUT = 'lockout';

    public const NOT_ENROLLED = 'not_enrolled';

    public const UNAVAILABLE = 'unavailable';

    public const TIMEOUT = 'timeout';

    public function __construct(
        public bool $success,
        public ?string $id = null,
        public ?string $error = null,
    ) {}

    public function cancelled(): bool
    {
        return $this->error === self::CANCELLED;
    }
}
