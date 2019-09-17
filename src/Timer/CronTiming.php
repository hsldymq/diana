<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

use Cron\CronExpression;

/**
 * 兼容cron语法的计时触发.
 */
class CronTiming implements TimingInterface
{
    private $cronExpression;

    /**
     * @param string $cronExpression cron兼容的表达式,例如'* * * * *',
     *                                              *    *    *    *    *
     *                                              -    -    -    -    -
     *                                              |    |    |    |    |
     *                                              |    |    |    |    |
     *                                              |    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
     *                                              |    |    |    +---------- month (1 - 12)
     *                                              |    |    +--------------- day of month (1 - 31)
     *                                              |    +-------------------- hour (0 - 23)
     *                                              +------------------------- min (0 - 59)
     */
    public function __construct(string $cronExpression)
    {
        $this->cronExpression = CronExpression::factory($cronExpression);
    }

    public function getTimingTick(\DateTime $current, TickerInterface $ticker): int
    {
        $next = $this->cronExpression->getNextRunDate($current);
        $secDiff = $next->getTimestamp() - $current->getTimestamp();
        if ($secDiff <= 0) {
            return -1;
        }

        return intval(ceil($secDiff * $ticker->getTicksPerSec()));
    }

    public function isContinuous(): bool
    {
        return true;
    }
}