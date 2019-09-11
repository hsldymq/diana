<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

class Timer
{
    const TICKS_PER_SEC = 1;

    private $currentTick = 0;

    /**
     * @var array [
     *      $tick => [$job1, $job2, ...]
     * ]
     */
    private $tickJobs = [];

    /**
     * @var array [
     *      $jobID => [
     *          'timing' => (TimingInterface),
     *          'tick' => (integer|null),
     *          'indexInList' => (integer),
     *      ],
     * ]
     */
    private $jobInfo = [];

    /**
     * @var LoopInterface
     */
    private $eventLoop;

    /**
     * @var TimerInterface
     */
    private $timer;

    /**
     * @var callable function($jobID) {}
     */
    private $callback;

    public function __construct(LoopInterface $eventLoop, callable $callback)
    {
        $this->eventLoop = $eventLoop;
        $this->callback = $callback;
    }

    public function __destruct()
    {
        $this->stopTimer();
        $this->clearJobs();
    }

    public function startTimer()
    {
        if ($this->timer) {
            return;
        }

        $this->timer = $this->eventLoop->addPeriodicTimer(1 / self::TICKS_PER_SEC, [$this, 'tick']);
        foreach ($this->jobInfo as $jobID => $info) {
            $this->setNextTimingTick($jobID);
        }
    }

    public function stopTimer()
    {
        $this->eventLoop->cancelTimer($this->timer);
        $this->timer = null;
    }

    public function addJob(string $jobID, TimingInterface $timing)
    {
        $this->jobInfo[$jobID]['timing'] = $timing;
        $tick = $this->jobInfo[$jobID]['tick'] ?? null;
        if ($tick) {
            $index = $this->jobInfo[$jobID]['indexInList'];
            unset($this->tickJobs[$tick][$index]);
            $this->setNextTimingTick($jobID);
        }
    }

    public function cancelJob(string $jobID)
    {
        if (!isset($this->jobInfo[$jobID])) {
            return;
        }

        unset($this->jobInfo[$jobID]);
    }

    public function clearJobs()
    {
        $this->tickJobs = [];
        $this->jobInfo = [];
    }

    private function tick()
    {
        ++$this->currentTick;

        foreach ($this->tickJobs[$this->currentTick] ?? [] as $index => $jobID) {
            try {
                call_user_func($this->callback, $jobID);
            } finally {
                if (isset($this->jobInfo[$jobID])) {
                    unset($this->tickJobs[$this->currentTick][$index]);
                    $this->setNextTimingTick($jobID);
                }
            }
        }

        unset($this->tickJobs[$this->currentTick]);
    }

    private function setNextTimingTick($jobID)
    {
        if (!isset($jobID)) {
            return;
        }

        $tick = $this->jobInfo[$jobID]['timing'](new \DateTime('now'));
        if ($tick <= 0) {
            return;
        }

        $this->tickJobs[$tick][] = $jobID;
        $this->jobInfo[$jobID]['tick'] = $tick;
        $this->jobInfo[$jobID]['indexInList'] = count($this->tickJobs[$tick]) - 1;
    }
}