<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

interface TimingInterface
{
    public function getTimingTick(\DateTime $dt, TickerInterface $ticker): int;
}