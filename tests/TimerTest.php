<?php

declare(strict_types=1);

use Archman\Diana\Timer\Duration;
use Archman\Diana\Timer\PeriodicTiming;
use Archman\Diana\Timer\Timer;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory;

class TimerTest extends TestCase
{
    public function testContinuouslyPeriodicTimingTick()
    {
        $jobID1 = 'j1';
        $jobID2 = 'j2';
        $jobID3 = 'j3';

        $tickCounter = [
            $jobID1 => 0,
            $jobID2 => 0,
            $jobID3 => 0,
        ];
        $timer = new Timer(Duration::SECOND, Factory::create(), function ($jobID) use (&$tickCounter) {
            $tickCounter[$jobID] += 1;
        });
        $timer->addJob($jobID1, new PeriodicTiming(new DateInterval('PT1S'), true));
        $timer->addJob($jobID2, new PeriodicTiming(new DateInterval('PT2S'), true));
        $timer->addJob($jobID3, new PeriodicTiming(new DateInterval('PT5S'), true));
        $timer->start();
        for ($i = 0; $i < 1000; $i++) {
            $timer->tick();
            $timer->finish($jobID1);
            if ($i % 4 === 3) {
                $timer->finish($jobID2);
            }
            if ($i % 10 === 9) {
                $timer->finish($jobID3);
            }
        }

        $this->assertEquals(1000, $tickCounter[$jobID1]);
        $this->assertEquals(250, $tickCounter[$jobID2]);
        $this->assertEquals(100, $tickCounter[$jobID3]);


        $tickCounter = [
            $jobID1 => 0,
            $jobID2 => 0,
            $jobID3 => 0,
        ];
        $timer = new Timer(100 * Duration::MILLISECOND, Factory::create(), function ($jobID) use (&$tickCounter) {
            $tickCounter[$jobID] += 1;
        });
        $timer->addJob($jobID1, new PeriodicTiming(new DateInterval('PT1S'), true));
        $timer->addJob($jobID2, new PeriodicTiming(new DateInterval('PT2S'), true));
        $timer->addJob($jobID3, new PeriodicTiming(new DateInterval('PT5S'), true));
        $timer->start();
        for ($i = 1; $i <= 10000; $i++) {
            $timer->tick();
            $timer->finish($jobID1);
            if ($i % 20 === 19) {
                $timer->finish($jobID2);
            }
            if ($i % 50 === 49) {
                $timer->finish($jobID3);
            }
        }

        $this->assertEquals(1000, $tickCounter[$jobID1]);
        $this->assertEquals(500, $tickCounter[$jobID2]);
        $this->assertEquals(200, $tickCounter[$jobID3]);
    }

    public function testIncontinuouslyPeriodicTimingTick()
    {
        $jobID1 = 'j1';
        $jobID2 = 'j2';
        $jobID3 = 'j3';

        $tickCounter = [
            $jobID1 => 0,
            $jobID2 => 0,
            $jobID3 => 0,
        ];
        $timer = new Timer(Duration::SECOND, Factory::create(), function ($jobID) use (&$tickCounter) {
            $tickCounter[$jobID] += 1;
        });
        $timer->addJob($jobID1, new PeriodicTiming(new DateInterval('PT1S'), false));
        $timer->addJob($jobID2, new PeriodicTiming(new DateInterval('PT2S'), false));
        $timer->addJob($jobID3, new PeriodicTiming(new DateInterval('PT5S'), false));
        $timer->start();
        for ($i = 1; $i <= 1000; $i++) {
            $timer->tick();
            $timer->finish($jobID1);
            if ($i % 10 <= 5) {
                $timer->finish($jobID2);
            }
            if ($i % 10 === 0) {
                $timer->finish($jobID3);
            }
        }

        $this->assertEquals(1000, $tickCounter[$jobID1]);
        $this->assertEquals(300, $tickCounter[$jobID2]);
        $this->assertEquals(100, $tickCounter[$jobID3]);
    }
}