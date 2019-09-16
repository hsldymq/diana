<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

class PeriodicTiming implements TimingInterface
{
    private $interval;

    private $isContinuous;

    public function __construct(\DateInterval $interval, bool $isContinuous)
    {
        $this->interval = $interval;
        $this->isContinuous = $isContinuous;
    }

    public function getTimingTick(\DateTime $current, TickerInterface $ticker): int
    {
        $currentTimestamp = $current->getTimestamp();
        $secDiff = $current->add($this->interval)->getTimestamp() - $currentTimestamp;

        return intval(ceil($secDiff * $ticker->getTicksPerSec()));
    }

    public function isContinuous(): bool
    {
        return $this->isContinuous;
    }
}