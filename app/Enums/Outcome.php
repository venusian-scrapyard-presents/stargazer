<?php

namespace App\Enums;

/**
 * How a screen's request ended, as the status row shows it.
 */
enum Outcome
{
    case DONE;
    case EMPTY;
    case INVALID;
    case FAILED;
    case RATE_LIMITED;

    public function defaultMessage(): string
    {
        return match ($this) {
            self::DONE => '',
            self::EMPTY => 'Nothing came back.',
            self::INVALID => 'Check the form.',
            self::FAILED => 'The request failed.',
            self::RATE_LIMITED => 'NASA rate limit hit — set a key of your own in Settings.',
        };
    }

    /**
     * Whether the status row shows it as an error.
     * @return bool
     */
    public function isError(): bool
    {
        return in_array($this, [self::INVALID, self::FAILED, self::RATE_LIMITED], true);
    }
}
