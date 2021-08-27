<?php

declare(strict_types=1);

namespace Archman\Diana\Timer\Timing;

use Archman\Diana\Timer\TickerInterface;
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
        $this->cronExpression = new CronExpression($cronExpression);
    }

    public function getTimingTick(\DateTime $dt, TickerInterface $ticker): int
    {
        $next = $this->cronExpression->getNextRunDate($dt);
        $secDiff = $next->getTimestamp() - $dt->getTimestamp();
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