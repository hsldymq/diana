<?php

declare(strict_types=1);

namespace Archman\Diana\Job\Repeater;

/**
 * 这个repeater会按照指定的值重复执行若干次.
 *
 * 例如,指定5次,那么当job第一次运行之后,会再重复执行5次之后正常结束.
 *
 * 如果执行job的进程收到主进程发来的停止消息,也会正常结束.
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

    public function isRepeatable(\DateTimeInterface $now): bool
    {
        return $this->remainTimes-- > 0;
    }

    public function getRepetitionInterval(): float
    {
        return $this->interval;
    }
}