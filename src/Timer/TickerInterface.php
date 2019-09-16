<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

interface TickerInterface
{
    public function getTickDuration(): int;

    public function getTicksPerSec(): float;
}