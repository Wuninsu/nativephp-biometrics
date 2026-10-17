<?php

namespace Wuninsu\NativeBiometrics;

/**
 * Whether the phone can show a biometric prompt right now.
 */
final class BiometricStatus
{
    /**
     * @param  string  $biometry  face, fingerprint, iris, optic, multiple, none or unknown
     * @param  string|null  $reason  why it is not available: not_enrolled, no_hardware, unavailable, lockout,
     *                               no_passcode, unsupported, or plugin_missing
     * @param  bool  $deviceSecure  the phone has a PIN, pattern or passcode (needed for allowDeviceCredential())
     */
    public function __construct(
        public readonly bool $available,
        public readonly string $biometry = 'none',
        public readonly ?string $reason = null,
        public readonly bool $deviceSecure = false,
    ) {}

    public static function unavailable(string $reason): self
    {
        return new self(false, 'none', $reason);
    }

    /**
     * @param  array<mixed>|null  $response
     */
    public static function fromResponse(?array $response): self
    {
        if ($response === null || ! array_key_exists('available', $response)) {
            return self::unavailable('plugin_missing');
        }

        return new self(
            $response['available'] === true,
            is_string($response['biometry'] ?? null) ? $response['biometry'] : 'unknown',
            is_string($response['reason'] ?? null) ? $response['reason'] : null,
            ($response['deviceSecure'] ?? false) === true,
        );
    }
}
