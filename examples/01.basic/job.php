<?php

require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/BasicJob.php';

use Archman\Diana\Agent;
use Archman\Diana\AgentFactory;
use Archman\Diana\Diana;
use Archman\Diana\Timer\PeriodicTiming;

$factory = new AgentFactory();
$factory->registerEvent('start', function (Agent $agent) {
    echo "Agent {$agent->getWorkerID()} Started.\n";
});
$master = (new Diana($factory))
    ->addJob('1', new BasicJob('1'), new PeriodicTiming(new DateInterval('PT1S')))
    ->addJob('2', new BasicJob('2'), new PeriodicTiming(new DateInterval('PT5S')))
    ->addJob('3', new BasicJob('3'), new PeriodicTiming(new DateInterval('PT10S')))
    ->addJob('4', new BasicJob('4'), new PeriodicTiming(new DateInterval('PT30S')))
    ->addJob('5', new BasicJob('5'), new PeriodicTiming(new DateInterval('PT1M')))
    ->on('shutdown', function (Diana $master) {
        echo "Master Shutdown.\n";
    })
    ->on('agentExit', function (string $agentID, int $pid, Diana $master) {
        echo "Agent {$agentID} Exit, PID: {$pid}.\n";
    });
$master->addSignalHandler(SIGINT, function () use ($master) {
    $master->shutdown();
});
$master->run();