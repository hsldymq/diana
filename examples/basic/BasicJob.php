<?php

use Archman\Diana\Job\JobInterface;

class BasicJob implements JobInterface
{
    private $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function execute()
    {
    }
}