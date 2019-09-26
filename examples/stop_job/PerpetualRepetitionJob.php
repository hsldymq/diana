<?php

use Archman\Diana\Agent;
use Archman\Diana\Job\CountdownRepeater;
use Archman\Diana\Job\JobInterface;
use Archman\Diana\Job\PerpetualRepeater;
use Archman\Diana\Job\RepetitionInterface;
use Archman\Diana\Job\RepeaterInterface;

class PerpetualRepetitionJob implements JobInterface, RepetitionInterface
{
    private $name;

    public function __construct()
    {
        $this->name = md5(time());
    }

    public function execute()
    {
    }

    public function getRepeater(): RepeaterInterface
    {
        return new PerpetualRepeater(1);
    }
}