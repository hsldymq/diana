<?php

use Archman\Diana\Timer\Duration;
use Archman\Diana\Timer\PeriodicTiming;
use Archman\Diana\Timer\Timer;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory;

class TimerTest extends TestCase
{
    public function testTick()
    {
        $jobID1 = 'j1';
        $jobID2 = 'j2';
        $jobID3 = 'j3';
        $jobID4 = 'j4';
        $jobID5 = 'j5';
        $tickCounter = [
            $jobID1 => 0,
            $jobID2 => 0,
            $jobID3 => 0,
            $jobID4 => 0,
            $jobID5 => 0,
        ];
        $timer = new Timer(Duration::SECOND, Factory::create(), function ($jobID) use (&$tickCounter) {
            $tickCounter[$jobID] += 1;
        });
        $timer->addJob($jobID1, new PeriodicTiming(new DateInterval('PT1S')));
        $timer->addJob($jobID2, new PeriodicTiming(new DateInterval('PT5S')));
        $timer->addJob($jobID3, new PeriodicTiming(new DateInterval('PT10S')));
        $timer->addJob($jobID4, new PeriodicTiming(new DateInterval('PT30S')));
        $timer->addJob($jobID5, new PeriodicTiming(new DateInterval('PT1M')));
        $timer->start();
        for ($i = 0; $i < 1806; $i++) {
            $timer->tick();
        }

        $this->assertEquals(1806, $tickCounter[$jobID1]);
        $this->assertEquals(361, $tickCounter[$jobID2]);
        $this->assertEquals(180, $tickCounter[$jobID3]);
        $this->assertEquals(60, $tickCounter[$jobID4]);
        $this->assertEquals(30, $tickCounter[$jobID5]);


        $tickCounter = [
            $jobID1 => 0,
            $jobID2 => 0,
            $jobID3 => 0,
            $jobID4 => 0,
            $jobID5 => 0,
        ];
        $timer = new Timer(100 * Duration::MILLISECOND, Factory::create(), function ($jobID) use (&$tickCounter) {
            $tickCounter[$jobID] += 1;
        });
        $timer->addJob($jobID1, new PeriodicTiming(new DateInterval('PT1S')));
        $timer->addJob($jobID2, new PeriodicTiming(new DateInterval('PT5S')));
        $timer->addJob($jobID3, new PeriodicTiming(new DateInterval('PT10S')));
        $timer->addJob($jobID4, new PeriodicTiming(new DateInterval('PT30S')));
        $timer->addJob($jobID5, new PeriodicTiming(new DateInterval('PT1M')));
        $timer->start();
        for ($i = 0; $i < 18060; $i++) {
            $timer->tick();
        }

        $this->assertEquals(1806, $tickCounter[$jobID1]);
        $this->assertEquals(361, $tickCounter[$jobID2]);
        $this->assertEquals(180, $tickCounter[$jobID3]);
        $this->assertEquals(60, $tickCounter[$jobID4]);
        $this->assertEquals(30, $tickCounter[$jobID5]);
    }
}