<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

class Duration
{
    const MILLISECOND = 1;

    const SECOND = 1000 * self::MILLISECOND;

    const MINTUE = 60 * self::SECOND;

    const HOUR = 60 * self::MINTUE;

    const DAY = 24 * self::HOUR;
}