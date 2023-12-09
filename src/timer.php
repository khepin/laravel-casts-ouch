<?php

declare(strict_types=1);

use SebastianBergmann\Timer\Duration;

/**
 * Usage:
 * $t = new Timer();
 * // ... thing you are trying to time
 * $t->stop()->format('time taken to eat breakfast: %s');
 */
class Timer
{
    protected $start;

    protected $stop;

    public function __construct()
    {
        $this->start = \hrtime(true);
    }

    public function stop(): Timer
    {
        $this->stop = \hrtime(true);

        return $this;
    }

    public function format(string $format = '%s'): string
    {
        $nano = $dur = $this->stop - $this->start;
        if ($dur < 1000) {
            return sprintf($format, $dur.' ns');
        }
        $dur = $dur / 1000.0;
        if ($dur < 1000) {
            return sprintf($format, sprintf('%.2f', $dur).' μs');
        }
        $dur = $dur / 1000.0;
        if ($dur < 1000) {
            return sprintf($format, sprintf('%.2f', $dur).' ms');
        }
        $dur = $dur / 1000.0;
        if ($dur < 1000) {
            return sprintf($format, sprintf('%.2f', $dur).' s');
        }

        return sprintf($format, Duration::fromNanoseconds($nano)->asString());
    }

    /**
     * Dumps the time it took to execute the callback
     */
    public static function td(callable $cb, string $name = 'time dump'): mixed
    {
        $t = new self;
        $r = $cb();

        dump($t->stop()->format("$name = %s"));

        return $r;
    }
}
