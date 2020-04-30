<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stop_job/PerpetualRepetitionJob.php';

use Archman\Diana\Agent;
use Archman\Diana\AgentFactory;
use Archman\Diana\Diana;
use Archman\Diana\Timer\Timing\PeriodicTiming;

$factory = (new AgentFactory())
    ->registerEvent('error', function (\Throwable $e) {
        echo "Agent Error: {$e->getMessage()}\n";
    })
    ->registerEvent('executed', function (string $jobID, int $startedAt, float $runtime, Agent $agent) {
        $dt = new DateTime("@{$startedAt}");
        echo "Job {$jobID} Executed By Agent {$agent->getAgentID()} At {$dt->format('Y-m-d H:i:s')}, runtime: {$runtime} Seconds. $startedAt\n";
    });

$master = (new Diana($factory))
    ->on('shutdown', function (Diana $master) {
        echo "Master Shutdown.\n";
    })
    ->on('start', function (Diana $master) {
        $master->addJob('a', new PerpetualRepetitionJob('a'), new PeriodicTiming(new DateInterval('PT1S'), true));
        $master->addJob('b', new PerpetualRepetitionJob('b'), new PeriodicTiming(new DateInterval('PT2S'), true));
        $master->addJob('c', new PerpetualRepetitionJob('c'), new PeriodicTiming(new DateInterval('PT3S'), true));
    })
    ->on('agentExit', function (string $agentID, int $pid, Diana $master) {
        echo "Agent {$agentID} Exit, PID: {$pid}.\n";
    })
    ->addSignalHandler(SIGQUIT, function (int $signal, Diana $master) {
        $master->removeJob('a');
        $master->removeJob('b');
        $master->removeJob('c');
        $master->addJob('a', new PerpetualRepetitionJob('a'), new PeriodicTiming(new DateInterval('PT1S'), true));
        $master->addJob('b', new PerpetualRepetitionJob('b'), new PeriodicTiming(new DateInterval('PT2S'), true));
        $master->addJob('c', new PerpetualRepetitionJob('c'), new PeriodicTiming(new DateInterval('PT3S'), true));
    })
    ->addSignalHandler(SIGINT, function (int $signal, Diana $master) {
        $master->shutdown();
    });
$master->run();