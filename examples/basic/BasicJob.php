<?php

use Archman\Diana\Agent;
use Archman\Diana\Job\JobInterface;

class BasicJob implements JobInterface
{
    private $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function execute(Agent $agent)
    {
        $dt = new DateTime('now');
        echo "Job {$this->id} Executed. {$dt->format('Y-m-d H:i:s')}\n";
    }
}