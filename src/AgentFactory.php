<?php

declare(strict_types=1);

namespace Archman\Diana;

use Archman\Whisper\AbstractWorker;
use Archman\Whisper\Interfaces\WorkerFactoryInterface;

class AgentFactory implements WorkerfactoryInterface
{
    /**
     * @var array [
     *      [$event, $handler],
     *      ...
     * ]
     */
    private $eventHandlers = [];

    public function makeWorker(string $id, $socketFD): AbstractWorker
    {
        $agent = new Agent($id, $socketFD);

        foreach ($this->eventHandlers as $each) {
            $agent->on($each[0], $each[1]);
        }

        return $agent;
    }

    /**
     * 注册agent的事件
     *
     * @param string $event
     * @param callable $handler
     *
     * @return self
     */
    public function registerEvent(string $event, callable $handler): self
    {
        $this->eventHandlers[] = [$event, $handler];

        return $this;
    }
}