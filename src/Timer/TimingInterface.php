<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

interface TimingInterface
{
    /**
     * 获得下一次触发job的tick值.
     *
     * @param \DateTime $dt
     * @param TickerInterface $ticker
     *
     * @return int
     */
    public function getTimingTick(\DateTime $dt, TickerInterface $ticker): int;

    /**
     * 是否连续计时.
     *
     * true: 当一个job到时被触发时,立即开始下一次tick的计时,不用等待job完成后再计时.
     * false: job触发后等到job执行完成后再进行下一次的计时
     *
     * @return bool
     */
    public function isContinuous(): bool;
}