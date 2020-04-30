<?php

use Archman\Diana\Job\JobInterface;
use Archman\Diana\Job\Repeater\PerpetualRepeater;
use Archman\Diana\Job\RepetitionInterface;
use Archman\Diana\Job\Repeater\RepeaterInterface;

class PerpetualRepetitionJob implements JobInterface, RepetitionInterface
{
    private $jobID;

    private $count = 0;

    public function __construct(string $jobID)
    {
        $this->jobID = $jobID;
    }

    public function execute()
    {
        $this->count++;
        echo "job {$this->jobID}: execute method called, count: {$this->count}\n";
    }

    public function getRepeater(): RepeaterInterface
    {
        return new PerpetualRepeater(1);
    }
}