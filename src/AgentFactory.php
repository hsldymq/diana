<?php

declare(strict_types=1);

namespace Archman\Diana;

use Archman\Whisper\AbstractWorker;
use Archman\Whisper\Interfaces\WorkerFactoryInterface;

class AgentFactory implements WorkerfactoryInterface
{
    public function makeWorker(string $id, $socketFD): AbstractWorker
    {
        return new Agent($id, $socketFD);
    }
}