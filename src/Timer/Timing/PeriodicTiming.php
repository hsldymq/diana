<?php

declare(strict_types=1);

namespace Archman\Diana\Timer\Timing;

use Archman\Diana\Timer\TickerInterface;

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
     *                           当为false时, 会等待job完成之后在开始新一轮计时.
     *                           false主要针对类似于while (true) { doSomething; sleep($x); }这种模式的死循环逻辑,提供等价的形式.
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