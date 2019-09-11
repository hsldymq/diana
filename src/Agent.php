<?php

declare(strict_types=1);

namespace Archman\Diana;

use Archman\Whisper\AbstractWorker;
use Archman\Whisper\Message;
use React\EventLoop\TimerInterface;

/**
 * 预定义事件列表:
 * @event start             agent子进程启动
 *                          参数: \Archman\Diana\Agent $agent
 *
 * @event error             发生错误
 *                          参数: \Throwable $ex, \Archman\Diana\Agent $agent
 */
class Agent extends AbstractWorker
{
    const STATE_RUNNING = 1;
    const STATE_SHUTDOWN = 3;

    /**
     * @var int
     */
    private $state = self::STATE_SHUTDOWN;

    /**
     * @var bool 是否空闲退出
     */
    private $idleWait = false;

    /**
     * @var int 空闲退出的最长空闲时间(秒)
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
                $this->errorlessEmit('error', [new \Exception('disconnected with Master')]);
                break;
            }
        }
    }

    public function handleMessage(Message $msg)
    {
        $this->clearShutdownTimer();

        switch ($msg->getType()) {
            case MessageTypeEnum::NORMAL_JOB:
                try {
                    $data = $this->decodeMessage($msg->getContent());
                } catch (\Throwable $e) {
                    $this->errorlessEmit('error', [$e]);
                    goto finished;
                }

                if (!isset($data['job'])) {
                    $this->errorlessEmit('error', [new \Exception('lack of job field in the message')]);
                    goto finished;
                }

                $obj = @unserialize($data['job']);
                if (!($obj instanceof JobInterface)) {
                    $this->errorlessEmit('error', [new \Exception("not job object: {$data['job']}")]);
                    goto finished;
                }

                try {
                    $obj->execute();
                } catch (\Throwable $e) {
                    $this->errorlessEmit('error', [$e]);
                }

                finished:
                $this->sendMessage(new Message(MessageTypeEnum::JOB_FINISHED, ''));
                // 如果没有设置等待时间,则立即退出
                if (!$this->idleWait) {
                    $this->sendMessage(new Message(MessageTypeEnum::STOP_SENDING, ''));
                }

                break;
            case MessageTypeEnum::LAST_MSG:
                if ($this->passiveShutdown) {
                    $this->sendMessage(new Message(MessageTypeEnum::KILL_ME, ''));
                } else {
                    $this->stopProcess();
                    $this->state = self::STATE_SHUTDOWN;
                }
                break;
            default:
                $this->errorlessEmit('error', [new \Exception("undefined message type: {$msg->getType()}")]);
        }

        $this->trySetShutdownTimer();
    }

    /**
     * 设置agent的空闲等待一定时间后如果没有任务分配再退出.
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
}