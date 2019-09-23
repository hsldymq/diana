<?php

declare(strict_types=1);

namespace Archman\Diana\Job;

/**
 * 永久重复.
 * 直到收到主进程发来退出或停止信号.
 */
class PerpetualRepeater implements RepeaterInterface
{
    private $interval;

    /**
     * @param float $interval job执行结束到下一次重复执行的间隔时间(单位: 秒)
     */
    public function __construct(float $interval)
    {
        $this->interval = $interval;
    }

    public function isRepeatable(\DateTimeInterface $_): bool
    {
        return true;
    }

    public function getRepetitionInterval(): float
    {
        return $this->interval;
    }
}