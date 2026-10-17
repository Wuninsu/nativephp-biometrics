<?php

namespace Wuninsu\NativeBiometrics;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Native\Mobile\Concerns\HandlesNativeCallbacks;
use Wuninsu\NativeBiometrics\Events\Completed;

/**
 * A biometric prompt being configured. Call start() to show it; the result arrives as a Completed event (or the
 * callback given to completed()).
 *
 * @method $this completed(\Closure|array|string $callback)
 */
class PendingPrompt
{
    use HandlesNativeCallbacks;

    protected ?string $id = null;

    protected string $eventClass = Completed::class;

    protected string $title = 'Unlock';

    protected ?string $subtitle = null;

    protected ?string $reason = null;

    protected string $cancelLabel = 'Cancel';

    protected bool $allowDeviceCredential = false;

    protected bool $started = false;

    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        return $this->id ??= (string) Str::uuid();
    }

    /** Heading of the Android prompt; on iOS also the reason when reason() is not set. */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /** Second line of the Android prompt. */
    public function subtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /** Text shown under Touch ID / after a failed Face ID on iOS. */
    public function reason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    /** Label of the cancel button. */
    public function cancelLabel(string $label): static
    {
        $this->cancelLabel = $label;

        return $this;
    }

    /** Let the phone's PIN, pattern or passcode stand in when biometrics fails or is not set up. */
    public function allowDeviceCredential(bool $allow = true): static
    {
        $this->allowDeviceCredential = $allow;

        return $this;
    }

    /** Send the result as another event class (constructor: bool $success, ?string $id, ?string $error). */
    public function event(string $eventClass): static
    {
        if (! class_exists($eventClass)) {
            throw new InvalidArgumentException("Event class {$eventClass} does not exist.");
        }

        $this->eventClass = $eventClass;

        return $this;
    }

    /**
     * Show the prompt. True when the phone started it; the outcome arrives later as the event.
     */
    public function start(): bool
    {
        if ($this->started || ! function_exists('nativephp_call')) {
            return false;
        }

        $this->started = true;

        $response = nativephp_call(Biometrics::BRIDGE.'.Prompt', json_encode(array_filter([
            'id' => $this->getId(),
            'event' => $this->eventClass,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'reason' => $this->reason ?? $this->title,
            'cancel' => $this->cancelLabel,
            'allowDeviceCredential' => $this->allowDeviceCredential,
        ], fn ($value) => $value !== null), JSON_THROW_ON_ERROR));

        $decoded = is_string($response) ? json_decode($response, true) : null;

        return is_array($decoded) && ($decoded['started'] ?? false) === true;
    }
}
