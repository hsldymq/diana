<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/basic/BasicJob.php';

use Archman\Diana\Agent;
use Archman\Diana\AgentFactory;
use Archman\Diana\Diana;
use Archman\Diana\Timer\Timing\PeriodicTiming;
use Archman\Diana\Timer\Timing\CronTiming;

$factory = (new AgentFactory())
    ->registerEvent('error', function (\Throwable $e) {
        echo "Agent Error: {$e->getMessage()}\n";
    })
    ->registerEvent('executed', function (string $jobID, int $executedAt, float $runtime, Agent $agent) {
        $dt = new DateTime("@{$executedAt}");
        echo "Job {$jobID} Executed By Agent {$agent->getAgentID()} At {$dt->format('Y-m-d H:i:s')}, runtime: {$runtime} Seconds.\n";
    });

$master = (new Diana($factory))
    ->addJob('CronTiming 1', new BasicJob('1'), new CronTiming('* * * * *'))
    ->addJob('PeriodicTiming 1', new BasicJob('1'), new PeriodicTiming(new DateInterval('PT1S'), true))
    ->addJob('PeriodicTiming 2', new BasicJob('2'), new PeriodicTiming(new DateInterval('PT5S'), true))
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