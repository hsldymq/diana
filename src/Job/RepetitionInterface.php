<?php

declare(strict_types=1);

namespace Archman\Diana\Job;

interface RepetitionInterface
{
    public function getRepeater(): RepeaterInterface;
}
