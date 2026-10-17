<?php

namespace Wuninsu\NativeBiometrics\Facades;

use Illuminate\Support\Facades\Facade;
use Wuninsu\NativeBiometrics\BiometricStatus;
use Wuninsu\NativeBiometrics\PendingPrompt;

/**
 * @method static bool isAvailable()
 * @method static BiometricStatus status()
 * @method static PendingPrompt prompt()
 *
 * @see \Wuninsu\NativeBiometrics\Biometrics
 */
class Biometrics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Wuninsu\NativeBiometrics\Biometrics::class;
    }
}
