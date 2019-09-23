<?php

declare(strict_types=1);

namespace Archman\Diana\Job;

/**
 * 按次数重复.
 */
class CountdownRepeater implements RepeaterInterface
{
    private $remainTimes;

    private $interval;

    /**
     * @param int $maxRepeatTimes 最多重复次数
     * @param float $interval job执行结束到下一次重复执行的间隔时间(单位: 秒)
     */
    public function __construct(int $maxRepeatTimes, float $interval)
    {
        $this->remainTimes = $maxRepeatTimes;
        $this->interval = $interval;
    }

    public function isRepeatable(\DateTimeInterface $_): bool
    {
        return --$this->remainTimes > 0;
    }

    public function getRepetitionInterval(): float
    {
        return $this->interval;
    }
}