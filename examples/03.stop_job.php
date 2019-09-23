<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stop_job/PerpetualRepetitionJob.php';

use Archman\Diana\AgentFactory;
use Archman\Diana\Diana;
use Archman\Diana\Timer\PeriodicTiming;

$master = (new Diana(new AgentFactory()))
    ->on('shutdown', function (Diana $master) {
        echo "Master Shutdown.\n";
    })
    ->on('start', function (Diana $master) {
        $master->addJob('1', new PerpetualRepetitionJob(), new PeriodicTiming(new DateInterval('PT1S'), true));
    })
    ->on('agentExit', function (string $agentID, int $pid, Diana $master) {
        echo "Agent {$agentID} Exit, PID: {$pid}.\n";
    })
    ->addSignalHandler(SIGQUIT, function (int $signal, Diana $master) {
        $master->removeJob('1');
        $master->addJob('1', new PerpetualRepetitionJob(), new PeriodicTiming(new DateInterval('PT1S'), true));
    })
    ->addSignalHandler(SIGINT, function (int $signal, Diana $master) {
        $master->shutdown();
    });
$master->run();