<?php

use Archman\Diana\Timer\CronTiming;
use Archman\Diana\Timer\Duration;
use Archman\Diana\Timer\PeriodicTiming;
use Archman\Diana\Timer\TickerInterface;
use Archman\Diana\Timer\Timer;
use Cron\CronExpression;
use PHPUnit\Framework\TestCase;

class CronTimingTest extends TestCase
{
    public function testGetTimingTick()
    {
        $timing = new CronTiming('* * * * *');
        // next date time should be 2019-01-01 00:01:00
        $ticks = $timing->getTimingTick(new DateTime('2019-01-01 00:00:30'), $this->getSecTicker());
        $this->assertEquals(30, $ticks);

        $timing = new CronTiming('5 * 2-4 * * ');
        // next date time should be 2019-01-02 00:05:00
        $ticks = $timing->getTimingTick(new DateTime('2019-01-01 00:01:00'), $this->getSecTicker());
        $this->assertEquals(86400 + 240, $ticks);
        // next date time should be 2019-01-02 01:05:00
        $ticks = $timing->getTimingTick(new DateTime('2019-01-02 00:05:00'), $this->getSecTicker());
        $this->assertEquals(3600, $ticks);

        $timing = new CronTiming('4,8 * 2-4 * * ');
        // next date time should be 2019-01-02 00:04:00
        $ticks = $timing->getTimingTick(new DateTime('2019-01-01 00:01:00'), $this->getSecTicker());
        $this->assertEquals(86400 + 180, $ticks);
        // next date time should be 2019-01-02 00:08:00
        $ticks = $timing->getTimingTick(new DateTime('2019-01-02 00:04:00'), $this->getSecTicker());
        $this->assertEquals(240, $ticks);

        $timing = new CronTiming('*/5 * 2-4 * * ');
        // next date time should be 2019-01-02 00:00:00
        $ticks = $timing->getTimingTick(new DateTime('2019-01-01 00:01:00'), $this->getSecTicker());
        $this->assertEquals(86400 - 60, $ticks);
        // next date time should be 2019-01-02 00:05:00
        $ticks = $timing->getTimingTick(new DateTime('2019-01-02 00:00:00'), $this->getSecTicker());
        $this->assertEquals(300, $ticks);
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
}