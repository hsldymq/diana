<?php

declare(strict_types=1);

namespace Archman\Diana\Job;

use Archman\Diana\Job\Repeater\RepeaterInterface;

interface RepetitionInterface
{
    public function getRepeater(): RepeaterInterface;
}
