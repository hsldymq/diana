<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

interface DateTimeProviderInterface
{
    public function getDateTime(): \DateTime;
}