<?php

use Archman\Diana\Timer\PeriodicTiming;
use Archman\Diana\Timer\Timer;
use PHPUnit\Framework\TestCase;

class PeriodicTimingTest extends TestCase
{
    public function testGetTimingTick()
    {
        $timing = new PeriodicTiming(new DateInterval('P500DT300S'));
        $ticks = $timing->getTimingTick(new DateTime());
        $this->assertEquals((86400 * 500 + 300) * Timer::TICKS_PER_SEC, $ticks);

        $timing = new PeriodicTiming(new DateInterval('P3Y5M'));
        $ticks = $timing->getTimingTick(new DateTime('2000-10-01T00:00:00'));
        $y3Secs = 365 * 3 * 86400;
        $m5Secs = (31 * 86400 + 30 * 86400 + 31 * 86400 + 31 * 86400 + 29 * 86400);
        $this->assertEquals(($y3Secs + $m5Secs) * Timer::TICKS_PER_SEC, $ticks);
    }
}