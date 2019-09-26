<?php

declare(strict_types=1);

namespace Archman\Diana\Job;

use Archman\Diana\Agent;

interface JobInterface
{
    public function execute();
}
