<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

/**
 * 周期计时触发.
 */
class PeriodicTiming implements TimingInterface
{
    private $interval;

    private $isContinuous;

    /**
     * @param \DateInterval $interval 周期间隔时长
     * @param bool $isContinuous 当为true时, 立刻开始对触发开始计时,不用等待job执行完成.
     */
    public function __construct(\DateInterval $interval, bool $isContinuous)
    {
        $this->interval = $interval;
        $this->isContinuous = $isContinuous;
    }

    public function getTimingTick(\DateTime $current, TickerInterface $ticker): int
    {
        $currentTimestamp = $current->getTimestamp();
        $secDiff = $current->add($this->interval)->getTimestamp() - $currentTimestamp;

        return intval(ceil($secDiff * $ticker->getTicksPerSec()));
    }

    public function isContinuous(): bool
    {
        return $this->isContinuous;
    }
}