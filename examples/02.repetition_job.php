<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/repetition_job/RepetitionJob.php';

use Archman\Diana\AgentFactory;
use Archman\Diana\Diana;
use Archman\Diana\Timer\PeriodicTiming;

$master = (new Diana(new AgentFactory()))
    ->addJob('1', new RepetitionJob(), new PeriodicTiming(new DateInterval('PT1S'), true))
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