<?php

declare(strict_types=1);

namespace Archman\Diana;

use Archman\Whisper\AbstractWorker;
use Archman\Whisper\Message;
use React\EventLoop\TimerInterface;

/**
 * 预定义事件列表:
 * @event start             worker启动
 *                          参数: \Archman\Diana\Agent $agent
 *
 * @event error             发生错误
 *                          参数: string $reason, \Throwable $ex, \Archman\Diana\Agent $agent
 *                          $reason enum:
 *                              'decodingMessage'           解码非预定义消息时结构错误
 *                              'undefinedJob'              消息中没有携带job信息
 *                              'unrecognizedJob'           无法反序列化为Job对象
 *                              'executingJob'              执行job逻辑出现错误
 *                              'undefinedMessage'          Diana发来的消息无法识别
 *                              'disconnected'              与Diana断开连接
 *                              'unrecoverable'             当出现了不可恢复的错误,即将退出时
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
    private $idleShutdown = false;

    /**
     * @var int 空闲退出的最长空闲时间(秒)
     */
    private $idleShutdownSec = 0;

    /**
     * @var TimerInterface
     */
    private $shutdownTimer = null;

    /**
     * @var bool 是否被动关闭(被动关闭是指由Diana杀死Agent进程)
     */
    private $passiveShutdown = false;

    /**
     * @var float 进行一次巡逻的间隔周期(秒)
     */
    private $patrolPeriod = 60.0;

    public function __construct(string $id, $socketFD)
    {
        parent::__construct($id, $socketFD);

        $this->setIdleShutdown(60);
        $this->trySetShutdownTimer();
    }

    public function run()
    {
        if ($this->state !== self::STATE_SHUTDOWN) {
            return;
        }

        $this->errorlessEmit('start');

        $this->state = self::STATE_RUNNING;
        while ($this->state !== self::STATE_SHUTDOWN) {
            try {
                $this->process($this->patrolPeriod);
            } catch (\Throwable $e) {
                $this->errorlessEmit('error', ['unrecoverable', $e]);
                break;
            }

            if (!$this->getCommunicator()->isReadable() && !$this->getCommunicator()->isWritable()) {
                $this->errorlessEmit('error', ['disconnected', new \Exception('disconnected with Diana')]);
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
                    $this->errorlessEmit('error', ['decodingMessage', $e]);
                    goto finished;
                }

                if (!isset($data['job'])) {
                    $this->errorlessEmit('error', ['undefinedJob', new \Exception('lack of job field in the message')]);
                    goto finished;
                }

                $obj = @unserialize($data['job']);
                if (!($obj instanceof JobInterface)) {
                    $this->errorlessEmit('error', ['unrecognizedJob', new \Exception("not job object: {$data['job']}")]);
                    goto finished;
                }

                try {
                    $obj->execute();
                } catch (\Throwable $e) {
                    $this->errorlessEmit('error', ['executingJob']);
                }

                finished:
                $this->sendMessage(new Message(MessageTypeEnum::JOB_FINISHED, ''));

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
                $this->errorlessEmit('error', ['undefinedMessage', new \Exception("undefined message type: {$msg->getType()}")]);
        }

        $this->trySetShutdownTimer();
    }

    /**
     * 设置worker的空闲退出的最大空闲时间(秒).
     *
     * @param int $seconds 必须大于0,否则设置无效
     */
    public function setIdleShutdown(int $seconds)
    {
        if ($seconds <= 0) {
            return;
        }

        $this->idleShutdown = true;
        $this->idleShutdownSec = $seconds;
    }

    /**
     * 不再允许空闲退出.
     */
    public function noIdleShutdown()
    {
        $this->idleShutdown = false;
        $this->idleShutdownSec = 0;
        $this->clearShutdownTimer();
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
        if (!$this->idleShutdown || $this->shutdownTimer) {
            return;
        }

        $this->shutdownTimer = $this->addTimer($this->idleShutdownSec, false, function () {
            $this->sendMessage(new Message(MessageTypeEnum::QUITING, ''));
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