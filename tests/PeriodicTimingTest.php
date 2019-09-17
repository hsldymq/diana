<?php

use Archman\Diana\Timer\Duration;
use Archman\Diana\Timer\PeriodicTiming;
use Archman\Diana\Timer\TickerInterface;
use Archman\Diana\Timer\Timer;
use PHPUnit\Framework\TestCase;

class PeriodicTimingTest extends TestCase
{
    public function testGetTimingTick()
    {
        $timing = new PeriodicTiming(new DateInterval('P500DT300S'), true);
        $ticks = $timing->getTimingTick(new DateTime(), $this->getSecTicker());
        $this->assertEquals(86400 * 500 + 300, $ticks);

        $timing = new PeriodicTiming(new DateInterval('P3Y5M'), true);
        $ticks = $timing->getTimingTick(new DateTime('2000-10-01T00:00:00'), $this->getSecTicker());
        $expect = 365 * 3 * 86400 + (31 * 86400 + 30 * 86400 + 31 * 86400 + 31 * 86400 + 29 * 86400);
        $this->assertEquals($expect, $ticks);

        $timing = new PeriodicTiming(new DateInterval('P3Y5MT1H50M30S'), true);
        $ticks = $timing->getTimingTick(new DateTime('2000-10-01T00:00:00'), $this->getMillisecTicker());
        $expect = 365 * 3 * 86400 * 1000
            + (31 * 86400 + 30 * 86400 + 31 * 86400 + 31 * 86400 + 29 * 86400) * 1000
            + 60 * 60 * 1000
            + 50 * 60 * 1000
            + 30 * 1000;
        $this->assertEquals($expect, $ticks);
    }

    private function getSecTicker()
    {
        return new class implements TickerInterface {
            public function getTicksPerSec(): float
            {
                return 1;
            }
        };
    }

    private function getMillisecTicker()
    {
        return new class implements TickerInterface {
            public function getTicksPerSec(): float
            {
                return 1000;
            }
        };
    }
}