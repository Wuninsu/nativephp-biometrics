<?php

namespace Wuninsu\NativeBiometrics;

use ReflectionFunction;

/**
 * Fingerprint, face and iris prompts (Android BiometricPrompt, iOS LocalAuthentication).
 *
 * The prompt only answers "is the phone's owner holding it?". The fingerprint or face never reaches the app.
 */
class Biometrics
{
    public const BRIDGE = 'WuninsuBiometrics';

    /**
     * True only inside a built app that contains this plugin.
     *
     * On a web server, in tests and under `native:jump`, `nativephp_call()` is NativePHP's PHP stand-in, not the
     * device's native function, so this returns false there.
     */
    public function isAvailable(): bool
    {
        if (! function_exists('nativephp_call') || ! (new ReflectionFunction('nativephp_call'))->isInternal()) {
            return false;
        }

        return ! function_exists('nativephp_can') || nativephp_can(self::BRIDGE.'.Prompt');
    }

    /**
     * Whether the phone can use biometrics now (hardware present and a fingerprint or face enrolled).
     */
    public function status(): BiometricStatus
    {
        if (! function_exists('nativephp_call')) {
            return BiometricStatus::unavailable('plugin_missing');
        }

        $response = nativephp_call(self::BRIDGE.'.Status', '{}');
        $decoded = is_string($response) ? json_decode($response, true) : null;

        return BiometricStatus::fromResponse(is_array($decoded) ? $decoded : null);
    }

    public function prompt(): PendingPrompt
    {
        return new PendingPrompt;
    }
}
