<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

class VoidTiming implements TimingInterface
{
    public function getTimingTick(\DateTime $current, TickerInterface $ticker): int
    {
        return -1;
    }
}