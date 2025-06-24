<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/repetition_job/RepetitionJob.php';

use Archman\Diana\Agent;
use Archman\Diana\AgentFactory;
use Archman\Diana\Diana;
use Archman\Diana\Timer\PeriodicTiming;

$factory = (new AgentFactory())
    ->registerEvent('error', function (\Throwable $e) {
        echo "Agent Error: {$e->getMessage()}\n";
    })
    ->registerEvent('jobError', function (string $jobID, int $startedAt, \Throwable $e) {
        echo "Job Execution Error: job {$jobID} {$e->getMessage()} as @{$startedAt}\n";
    })
    ->registerEvent('executed', function (string $jobID, int $startedAt, float $runtime, Agent $agent) {
        $dt = new DateTime("@{$startedAt}");
        echo "Job {$jobID} Executed By Agent {$agent->getAgentID()} At {$dt->format('Y-m-d H:i:s')}, runtime: {$runtime} Seconds. $startedAt\n";
    });

$master = (new Diana($factory))
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