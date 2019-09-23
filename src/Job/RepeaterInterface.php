<?php

declare(strict_types=1);

namespace Archman\Diana\Job;

interface RepeaterInterface
{
    /**
     * 是否可以再一次重复执行.
     *
     * @param \DateTimeInterface $current
     *
     * @return bool
     */
    public function isRepeatable(\DateTimeInterface $current): bool;

    /**
     * 在一次execute执行完成到下一次execute开始执行之间需要等待的间隔时间.
     *
     * @return float 单位: 秒
     */
    public function getRepetitionInterval(): float;
}