<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

interface TickerInterface
{
    /**
     * 返回每一秒的tick数
     *
     * @return float
     */
    public function getTicksPerSec(): float;
}