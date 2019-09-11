<?php

use Archman\Diana\JobInterface;

class BasicJob implements JobInterface
{
    private $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function execute()
    {
        $dt = new DateTime('now');
        echo "Job {$this->id} Executed. {$dt->format('Y-m-d H:i:s')}\n";
    }
}