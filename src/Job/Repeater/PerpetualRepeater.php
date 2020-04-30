<?php

declare(strict_types=1);

namespace Archman\Diana\Job\Repeater;

/**
 * 这个repeater会使得job永久重复执行.
 *
 * 直到执行job的进程收到主进程发来的停止执行的消息,job便会正常结束.
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