<?php

namespace Wuninsu\NativeBiometrics;

use Illuminate\Support\ServiceProvider;

class BiometricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Biometrics::class);
    }
}
