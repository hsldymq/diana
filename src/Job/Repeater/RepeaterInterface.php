<?php

declare(strict_types=1);

namespace Archman\Diana\Job\Repeater;

interface RepeaterInterface
{
    /**
     * 此次重复是否允许执行.
     *
     * @param \DateTimeInterface $now
     *
     * @return bool
     */
    public function isRepeatable(\DateTimeInterface $now): bool;

    /**
     * 在一次execute执行完成到下一次execute开始执行之间需要等待的间隔时间.
     *
     * @return float 单位: 秒
     */
    public function getRepetitionInterval(): float;
}