<?php

declare(strict_types=1);

namespace Archman\Diana\Timer\Timing;

use Archman\Diana\Timer\TickerInterface;

class VoidTiming implements TimingInterface
{
    public function getTimingTick(\DateTime $dt, TickerInterface $ticker): int
    {
        return -1;
    }

    public function isContinuous(): bool
    {
        return false;
    }
}