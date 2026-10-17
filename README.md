# NativePHP Biometrics

[![Latest Version](https://img.shields.io/packagist/v/wuninsu/nativephp-biometrics.svg)](https://packagist.org/packages/wuninsu/nativephp-biometrics)
[![License](https://img.shields.io/packagist/l/wuninsu/nativephp-biometrics.svg)](LICENSE)

Fingerprint, Face ID and Touch ID prompts for NativePHP Mobile v4 apps.

- **Android:** `androidx.biometric` `BiometricPrompt` (Class 3 / strong biometrics).
- **iOS:** `LocalAuthentication` (Face ID, Touch ID, Optic ID).

The prompt answers one question: is the phone's owner holding it? The fingerprint or face never reaches the app. To protect a secret behind the prompt, keep the secret itself in secure storage (for example `wuninsu/nativephp-secure-storage`), and use the prompt to decide whether the app may use it.

> **Early release (0.x).** The PHP side is covered by tests and the Android code compiles against NativePHP Mobile 4.6. Testing on real phones is in progress, so the API may still change before 1.0.

## Requirements

- PHP 8.3+
- `nativephp/mobile` ^4.6
- Android 8 (API 26) or newer, iOS 18.2 or newer

## Install

```sh
composer require wuninsu/nativephp-biometrics
```

NativePHP compiles only the plugins you register. If your app has no `app/Providers/NativeServiceProvider.php` yet, publish it first:

```sh
php artisan vendor:publish --tag=nativephp-plugins-provider
```

Then register the plugin and rebuild the native project:

```sh
php artisan native:plugin:register wuninsu/nativephp-biometrics
php artisan native:install --force
```

The plugin adds `NSFaceIDUsageDescription` ("Use Face ID to unlock the app.") to the iOS Info.plist. Apple requires it before Face ID can be used.

## Use

### Can the phone do it?

```php
use Wuninsu\NativeBiometrics\Facades\Biometrics;

$status = Biometrics::status();

$status->available;     // hardware present and a finger or face enrolled
$status->biometry;      // face, fingerprint, iris, optic, multiple, none, unknown
$status->reason;        // when not available: not_enrolled, no_hardware, unavailable, lockout, no_passcode, unsupported, plugin_missing
$status->deviceSecure;  // the phone has a PIN, pattern or passcode
```

### Show the prompt

The prompt runs on the phone's screen, so its result arrives later, as the `Wuninsu\NativeBiometrics\Events\Completed` event.

```php
use Wuninsu\NativeBiometrics\Events\Completed;

Biometrics::prompt()
    ->title('Unlock MyApp')
    ->subtitle('Confirm it is you')       // Android only
    ->reason('Unlock your account')       // iOS; defaults to the title
    ->cancelLabel('Use password')
    ->allowDeviceCredential()             // optional: the phone's PIN/passcode may stand in
    ->completed(function (Completed $event) {
        // $event->success, $event->error
    })
    ->start();
```

Or listen for the event anywhere (a Laravel listener, or `#[On]` / `#[OnNative]` in a component):

```php
use Wuninsu\NativeBiometrics\Events\Completed;

public function handle(Completed $event): void
{
    if ($event->success) {
        // unlocked
    } elseif ($event->cancelled()) {
        // the user chose cancel
    }
}
```

`$event->error` is null on success, otherwise one of: `cancelled`, `failed`, `lockout`, `not_enrolled`, `unavailable`, `timeout`. A single unrecognised finger or face does not end the prompt; the phone lets the user try again.

Use `->id('…')` to match the event to the prompt that started it, and `->event(MyEvent::class)` to receive another event class (its constructor takes `bool $success, ?string $id, ?string $error`).

### Only on the phone

The plugin's native code exists only in a real build (`php artisan native:run` or a release). It is **not** in the NativePHP Jump app, and there is no bridge on a web server or in tests. `Biometrics::isAvailable()` is true only inside a built app that contains this plugin.

## Testing

```php
use Native\Mobile\Testing\FakeBridge;

FakeBridge::enable()
    ->respondTo('WuninsuBiometrics.Status', ['available' => true, 'biometry' => 'fingerprint', 'deviceSecure' => true])
    ->respondTo('WuninsuBiometrics.Prompt', ['started' => true]);

// Simulate the phone's answer:
event(new Completed(success: true, id: 'unlock'));
```

## Bridge functions

| Function | Parameters | Returns |
|---|---|---|
| `WuninsuBiometrics.Status` | none | `{available, biometry, reason?, deviceSecure}` |
| `WuninsuBiometrics.Prompt` | `id`, `event`, `title`, `subtitle?`, `reason`, `cancel`, `allowDeviceCredential` | `{started: true}`, then the event `{success, id, error}` |

## License

MIT
