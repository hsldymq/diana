<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

interface TimingInterface
{
    public function getTimingTick(\DateTime $dt, Timer $timer): int;
}