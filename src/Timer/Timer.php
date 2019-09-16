<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

class Timer implements TickerInterface
{
    const TICKS_PER_SEC = 1;

    /**
     * @var int
     */
    private $currentTick;

    /**
     * @var int
     */
    private $tickDuration;

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

    public function __construct(int $tickDuration, LoopInterface $eventLoop, callable $callback)
    {
        $this->tickDuration = $tickDuration;
        $this->eventLoop = $eventLoop;
        $this->callback = $callback;
    }

    public function __destruct()
    {
        $this->stop();
        $this->clearJobs();
    }

    public function start()
    {
        if ($this->timer) {
            return;
        }

        $this->timer = $this->eventLoop->addPeriodicTimer(1 / $this->getTicksPerSec(), [$this, 'tick']);
        foreach ($this->jobInfo as $jobID => $info) {
            $this->setNextTimingTick($jobID);
        }
    }

    public function stop()
    {
        if ($this->timer) {
            $this->eventLoop->cancelTimer($this->timer);
        }
        $this->timer = null;
        $this->currentTick = 0;
        $this->tickJobs = [];
    }

    public function tick()
    {
        ++$this->currentTick;

        foreach ($this->tickJobs[$this->currentTick] ?? [] as $index => $jobID) {
            try {
                call_user_func($this->callback, $jobID);
            } finally {
                $this->setNextTimingTick($jobID);
            }
        }

        unset($this->tickJobs[$this->currentTick]);
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

    /**
     * 返回每秒的tick数.
     *
     * @return float
     */
    public function getTicksPerSec(): float
    {
        return floatval(Duration::SECOND / $this->tickDuration);
    }

    /**
     * 返回一次tick的时间间隔.
     *
     * 参考Duration类.
     *
     * @return int
     */
    public function getTickDuration(): int
    {
        return $this->tickDuration;
    }

    private function setNextTimingTick($jobID)
    {
        if (!isset($this->jobInfo[$jobID])) {
            return;
        }

        /** @var TimingInterface $timing */
        $timing = $this->jobInfo[$jobID]['timing'];
        $tick = $timing->getTimingTick($this->getCurrentDateTime(), $this);
        if ($tick <= 0) {
            return;
        }

        $nextTick = $this->currentTick + $tick;
        $this->tickJobs[$nextTick][] = $jobID;
        $this->jobInfo[$jobID]['tick'] = $tick;
        $this->jobInfo[$jobID]['indexInList'] = count($this->tickJobs[$nextTick]) - 1;
    }

    protected function getCurrentDateTime(): \DateTime
    {
        return new \DateTime('now');
    }
}