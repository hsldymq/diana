<?php

declare(strict_types=1);

namespace Archman\Diana\Job\Repeater;

/**
 * 这个repeater设置一个job运行指定时长后退出.
 *
 * 当指定一个时间长度(单位: 秒), 比如100秒, 在job开始执行后100秒内会重复执行.
 *
 * 当超过100秒后, 或执行job的进程收到主进程发来的停止消息, job会正常结束.
 */
class TimingRepeater implements RepeaterInterface
{
    private $startedAt = null;

    private $durationSec;

    private $interval;

    /**
     * @param \DateTimeInterface job开始运行的时间.
     * @param int $durationSec 持续时长(单位: 秒)
     * @param float $interval job执行结束到下一轮开始重复执行的间隔时间(单位: 秒)
     */
    public function __construct(\DateTimeInterface $startedAt, int $durationSec, float $interval)
    {
        $this->startedAt = $startedAt->getTimestamp();
        $this->durationSec = $durationSec;
        $this->interval = $interval;
    }

    public function isRepeatable(\DateTimeInterface $now): bool
    {
        if ($this->startedAt === null) {
            $this->startedAt = $now->getTimestamp();
        }

        return $now->getTimestamp() - $this->startedAt < $this->durationSec;
    }

    public function getRepetitionInterval(): float
    {
        return $this->interval;
    }
}