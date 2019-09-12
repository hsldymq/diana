<?php

require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/BasicJob.php';

use Archman\Diana\AgentFactory;
use Archman\Diana\Diana;
use Archman\Diana\Timer\PeriodicTiming;

$master = (new Diana(new AgentFactory()))
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
    })
    ->addSignalHandler(SIGINT, function (int $signal, Diana $master) {
        $master->shutdown();
    });
$master->run();