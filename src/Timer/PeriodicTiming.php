<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

class PeriodicTiming implements TimingInterface
{
    private $interval;

    public function __construct(\DateInterval $interval)
    {
        $this->interval = $interval;
    }

    public function getTimingTick(\DateTime $current, Timer $timer): int
    {
        $currentTimestamp = $current->getTimestamp();
        $secDiff = $current->add($this->interval)->getTimestamp() - $currentTimestamp;

        return intval(ceil($secDiff * $timer->getTicksPerSec()));
    }
}