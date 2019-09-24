<?php

use Archman\Diana\Agent;
use Archman\Diana\Job\CountdownRepeater;
use Archman\Diana\Job\JobInterface;
use Archman\Diana\Job\RepetitionInterface;
use Archman\Diana\Job\RepeaterInterface;

class RepetitionJob implements JobInterface, RepetitionInterface
{
    private $name;

    public function __construct()
    {
        $this->name = md5(time());
    }

    public function execute(Agent $agent)
    {
        $dt = new DateTime();
        echo "Agent: {$agent->getAgentID()}, Job {$this->name} Executed. {$dt->format('Y-m-d H:i:s')}\n";
    }

    public function getRepeater(): RepeaterInterface
    {
        return new CountdownRepeater(10, 1);
    }
}