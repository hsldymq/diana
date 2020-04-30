<?php

use Archman\Diana\Job\Repeater\CountdownRepeater;
use Archman\Diana\Job\JobInterface;
use Archman\Diana\Job\RepetitionInterface;
use Archman\Diana\Job\Repeater\RepeaterInterface;

class RepetitionJob implements JobInterface, RepetitionInterface
{
    private $count = 0;

    public function execute()
    {
        $this->count++;
        echo "execute method called, count: {$this->count}\n";
    }

    public function getRepeater(): RepeaterInterface
    {
        return new CountdownRepeater(10, 1);
    }
}