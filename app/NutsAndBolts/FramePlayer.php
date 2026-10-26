<?php

namespace App\NutsAndBolts;

/**
 * Which of a day's frames shows, and when the next one is due: it plays a loop, or holds while
 * the user steps.
 */
class FramePlayer
{
    /** Seconds each frame shows while playing, until setStep() says otherwise. */
    public const float STEP = 1.0;

    /** The speeds offered, label => seconds per frame, in menu order. */
    public const array SPEEDS = ['2 s per frame' => 2.0, '1 s per frame' => 1.0, '0.5 s per frame' => 0.5, '0.25 s per frame' => 0.25];

    protected float $step = self::STEP;

    protected int $count = 0;

    protected int $index = 0;

    protected bool $playing = false;

    /** When the shown frame came up, in seconds. */
    protected float $since = 0.0;

    /** A new day of $count frames: the first shows, and it plays when there is more than one. */
    public function load(int $count, float $now): void
    {
        $this->count = max(0, $count);
        $this->index = 0;
        $this->playing = false;
        $this->play($now);
    }

    /** The index in SPEEDS of the speed a player starts on. */
    public static function defaultSpeed(): int
    {
        return (int) array_search(self::STEP, array_values(self::SPEEDS), true);
    }

    public function step(): float
    {
        return $this->step;
    }

    /** Seconds each frame shows from now on; the shown frame's clock is not reset. */
    public function setStep(float $seconds): void
    {
        $this->step = max(0.05, $seconds);
    }

    public function index(): int
    {
        return $this->index;
    }

    public function isPlaying(): bool
    {
        return $this->playing;
    }

    /** Whether there is another frame to step or play to. */
    public function canStep(): bool
    {
        return $this->count > 1;
    }

    public function play(float $now): void
    {
        if ($this->canStep()) {
            $this->playing = true;
            $this->since = $now;
        }
    }

    public function pause(): void
    {
        $this->playing = false;
    }

    public function toggle(float $now): void
    {
        $this->playing ? $this->pause() : $this->play($now);
    }

    /** Pause and show the next frame; the last wraps to the first. */
    public function next(): int
    {
        return $this->shift(1);
    }

    /** Pause and show the frame before; the first wraps to the last. */
    public function previous(): int
    {
        return $this->shift(-1);
    }

    /**
     * While playing, the frame now due, once a step has passed since the shown one came up;
     * null when the shown frame stays.
     */
    public function advance(float $now): ?int
    {
        if (! $this->playing || $now - $this->since < $this->step) {
            return null;
        }

        $this->since = $now;
        $this->index = ($this->index + 1) % $this->count;

        return $this->index;
    }

    /** `frame N of M`; empty before a day has frames. */
    public function label(): string
    {
        return $this->count === 0 ? '' : sprintf('frame %d of %d', $this->index + 1, $this->count);
    }

    protected function shift(int $by): int
    {
        $this->pause();
        if ($this->canStep()) {
            $this->index = ($this->index + $by + $this->count) % $this->count;
        }

        return $this->index;
    }
}
