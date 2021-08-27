<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

class Duration
{
    public const MILLISECOND = 1;

    public const SECOND = 1000 * self::MILLISECOND;

    public const MINUTE = 60 * self::SECOND;

    public const HOUR = 60 * self::MINUTE;

    public const DAY = 24 * self::HOUR;
}