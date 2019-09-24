<?php

declare(strict_types=1);

namespace Archman\Diana;

use Archman\Diana\Job\JobInterface;
use Archman\Diana\Job\RepetitionInterface;
use Archman\Whisper\AbstractWorker;
use Archman\Whisper\Message;
use React\EventLoop\TimerInterface;

/**
 * 预定义事件列表:
 * @event start             agent子进程启动
 *                          参数: \Archman\Diana\Agent $agent
 *
 * @event executing         开始执行job
 *                          参数: string $jobID, \Archman\Diana\Agent $agent
 *
 * @event executed          job成功执行
 *                          参数: string $jobID, int $executedAt, float $runtime, \Archman\Diana\Agent $agent
 *                          $executedAt 代表job开始执行时的时间戳
 *                          $runtime 代表job执行时长, 单位:秒
 *
 * @event disconnected      已与主进程的连接断开
 *                          \Archman\Diana\Agent $agent
 *
 * @event error             发生错误
 *                          参数: \Throwable $ex, \Archman\Diana\Agent $agent
 */
class Agent extends AbstractWorker
{
    use TailingEventEmitterTrait;

    const STATE_RUNNING = 1;
    const STATE_SHUTTING = 2;
    const STATE_SHUTDOWN = 3;

    /**
     * @var int
     */
    private $state = self::STATE_SHUTDOWN;

    /**
     * @var bool 是否空闲等待
     */
    private $idleWait = false;

    /**
     * @var int 空闲等待最长时间后退出(秒)
     */
    private $idleWaitSec = 0;

    /**
     * @var TimerInterface
     */
    private $shutdownTimer = null;

    /**
     * @var bool 是否被动关闭(被动关闭是指由主进程通过信号杀死Agent子进程)
     */
    private $passiveShutdown = false;

    /**
     * @var float 进行一次巡逻的间隔周期(秒)
     */
    private $patrolPeriod = 60.0;

    /**
     * @var bool 是否处于job重复执行状态.
     */
    private $isInRepeatLoop = false;

    /**
     * @var string
     */
    private $currentJobID;

    public function __construct(string $id, $socketFD)
    {
        parent::__construct($id, $socketFD);
    }

    public function run()
    {
        if ($this->state !== self::STATE_SHUTDOWN) {
            return;
        }

        $this->trySetShutdownTimer();
        $this->errorlessEmit('start');

        $this->state = self::STATE_RUNNING;
        while ($this->state !== self::STATE_SHUTDOWN) {
            try {
                $this->process($this->patrolPeriod);
            } catch (\Throwable $e) {
                $this->errorlessEmit('error', [$e]);
                break;
            }

            if (!$this->getCommunicator()->isReadable() && !$this->getCommunicator()->isWritable()) {
                $this->errorlessEmit('disconnected');
                break;
            }
        }
    }

    /**
     * @param Message $msg
     *
     * @return void
     * @throws
     */
    public function handleMessage(Message $msg)
    {
        $this->clearShutdownTimer();

        switch ($msg->getType()) {
            case MessageTypeEnum::NORMAL_JOB:
                try {
                    $data = $this->decodeAndValidate($msg);
                } catch (\Throwable $e) {
                    $this->errorlessEmit('error', [$e]);
                    goto finished;
                }

                /** @var JobInterface $job */
                $job = $data['job'];
                $jobID = $data['jobID'];
                $this->errorlessEmit('executing', [$jobID]);
                try {
                    $this->currentJobID = $jobID;
                    $startAt = $this->getTime();
                    $this->executeJob($job);
                    $runtime = $this->getTime() - $startAt;
                    $this->errorlessEmit('executed', [$jobID, time(), $runtime]);
                } catch (\Throwable $e) {
                    $this->errorlessEmit('error', [$e]);
                } finally {
                    $this->currentJobID = null;
                }

                finished:
                $this->sendMessage(new Message(MessageTypeEnum::JOB_FINISHED, ''));
                if ($this->state === self::STATE_SHUTTING) {
                    $this->runShutdownProgression();
                } else {
                    // 如果没有设置等待时间,则立即退出
                    if (!$this->idleWait) {
                        $this->sendMessage(new Message(MessageTypeEnum::STOP_SENDING, ''));
                    }
                }

                break;
            case MessageTypeEnum::STOP_EXECUTING:
                try {
                    $data = $this->decodeMessage($msg->getContent());
                    if ($this->currentJobID !== $data['jobID']) {
                        throw new \Exception("can not stop job {$this->currentJobID}, incorrect job id: {$data['jobID']}");
                    }
                    $this->isInRepeatLoop = false;
                } catch (\Throwable $e) {
                    $this->errorlessEmit('error', [$e]);
                }

                break;
            case MessageTypeEnum::LAST_MSG:
                if ($this->isInRepeatLoop) {
                    $this->isInRepeatLoop = false;
                    $this->state = self::STATE_SHUTTING;
                } else {
                    $this->runShutdownProgression();
                }

                break;
            default:
                $this->errorlessEmit('error', [new \Exception("undefined message type: {$msg->getType()}")]);
        }

        $this->trySetShutdownTimer();
    }

    /**
     * 设置agent的空闲最大等待时间, 如果空闲超过此时间之后就退出.
     *
     * 如果未设置该值,那么默认情况下,agent执行了job之后就直接退出.
     *
     * @param int $seconds 必须大于0,否则设置无效
     */
    public function setIdleWait(int $seconds)
    {
        if ($seconds <= 0) {
            return;
        }

        $this->idleWait = true;
        $this->idleWaitSec = $seconds;
    }

    /**
     * 设置进程关闭模式.
     *
     * @param bool $isPassive true:被动模式, false:主动模式
     *
     * @return self
     */
    public function setShutdownMode(bool $isPassive): self
    {
        $this->passiveShutdown = $isPassive;

        return $this;
    }

    public function errorlessEmit(string $event, array $args = [])
    {
        try {
            $this->emit($event, $args);
        } finally {}
    }

    /**
     * @return string
     */
    public function getAgentID(): string
    {
        return $this->getWorkerID();
    }

    private function trySetShutdownTimer()
    {
        if (!$this->idleWait || $this->shutdownTimer) {
            return;
        }

        $this->shutdownTimer = $this->addTimer($this->idleWaitSec, false, function () {
            $this->sendMessage(new Message(MessageTypeEnum::STOP_SENDING, ''));
        });
    }

    private function clearShutdownTimer()
    {
        if ($this->shutdownTimer) {
            $this->removeTimer($this->shutdownTimer);
            $this->shutdownTimer = null;
        }
    }

    /**
     * @param string $content
     *
     * @return array
     * @throws \Exception
     */
    private function decodeMessage(string $content): array
    {
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(sprintf("Error:%s, Content:%s", json_last_error_msg(), $content));
        }

        return $decoded;
    }

    /**
     * @param Message $msg
     *
     * @return array
     * @throws
     */
    private function decodeAndValidate(Message $msg): array
    {
        $data = $this->decodeMessage($msg->getContent());

        if (!isset($data['job'])) {
            throw new \Exception('lack of job field in the message');
        }

        if (!isset($data['jobID'])) {
            throw new \Exception('lack of jobID field in the message');
        }

        $job = @unserialize($data['job']);
        if (!($job instanceof JobInterface)) {
            throw new \Exception("not job object: {$data['job']}");
        }

        $data['job'] = $job;

        return $data;
    }

    /**
     * @return float
     */
    private function getTime(): float
    {
        if (function_exists('hrtime')) {
            return hrtime(true) / 1e9;
        } else {
            return microtime(true);
        }
    }

    private function runShutdownProgression()
    {
        if ($this->passiveShutdown) {
            $this->sendMessage(new Message(MessageTypeEnum::KILL_ME, ''));
        } else {
            $this->stopProcess();
            $this->state = self::STATE_SHUTDOWN;
        }
    }

    /**
     * 运行job逻辑.
     *
     * 允许job重复执行,对实现了RepetitionInterface的job,会判断在一次运行完毕后是否可以继续重复运行.
     *
     * @param JobInterface $job
     *
     * @throws
     */
    private function executeJob(JobInterface $job)
    {
        if (!($job instanceof RepetitionInterface)) {
            $job->execute($this);
            return;
        }

        $this->stopProcess();
        $repeater = $job->getRepeater();
        $this->isInRepeatLoop = true;
        do {
            try {
                $job->execute($this);

                if (!$this->isInRepeatLoop ||
                    !$repeater->isRepeatable(new \DateTime()) ||
                    (!$this->getCommunicator()->isReadable() && !$this->getCommunicator()->isWritable())
                ) {
                    break;
                }

                $this->process($repeater->getRepetitionInterval());
            } catch (\Throwable $e) {
                $this->isInRepeatLoop = false;
                throw $e;
            }
        } while (true);
        $this->isInRepeatLoop = false;
    }
}