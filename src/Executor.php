<?php

declare(strict_types=1);

namespace Archman\Diana;

use Archman\Diana\Exception\ShutdownLoopException;
use Archman\Diana\Job\JobInterface;
use Archman\Diana\Job\RepetitionInterface;
use Archman\Whisper\Communicator;
use Archman\Whisper\Interfaces\MessageHandler;
use Archman\Whisper\Message;
use React\EventLoop\Factory;
use React\EventLoop\TimerInterface;
use React\Stream\DuplexResourceStream;

class Executor implements MessageHandler
{
    /** @var \React\EventLoop\LoopInterface */
    private $eventLoop;

    /** @var Communicator */
    private $communicator;

    /** @var TimerInterface */
    private $processTimer = null;

    /** @var bool 是否处于job重复执行状态. */
    private $inRepeatingLoop = false;

    /** @var string */
    private $currentJobID;

    public function __construct($socketFD)
    {
        $this->eventLoop = Factory::create();
        $stream = new DuplexResourceStream($socketFD, $this->eventLoop);
        $this->communicator = new Communicator($stream, $this);
    }

    public function __destruct()
    {
        unset($this->eventLoop);
        unset($this->communicator);
    }

    /**
     * 运行job逻辑.
     *
     * 允许job重复执行,对实现了RepetitionInterface的job,会判断在一次运行完毕后是否可以继续重复运行.
     *
     * @param string $jobID
     * @param JobInterface $job
     *
     * @throws
     */
    public function executeJob(string $jobID, JobInterface $job)
    {
        $this->currentJobID = $jobID;
        if (!($job instanceof RepetitionInterface)) {
            $job->execute();
            return;
        }

        $repeater = $job->getRepeater();
        $this->inRepeatingLoop = true;
        do {
            try {
                $job->execute();

                if (!$repeater->isRepeatable(new \DateTime()) ||
                    (!$this->communicator->isReadable() && !$this->communicator->isWritable())
                ) {
                    $this->finishRepeating();
                    break;
                }

                $this->process($repeater->getRepetitionInterval());
            } catch (\Throwable $e) {
                $this->finishRepeating();
                throw $e;
            }
        } while ($this->inRepeatingLoop);
        $this->currentJobID = null;
    }

    public function handleMessage(Message $msg)
    {
        switch ($msg->getType()) {
            case MessageTypeEnum::STOP_EXECUTING:
                $data = $this->decodeMessage($msg->getContent());
                if ($this->currentJobID !== $data['jobID']) {
                    throw new \Exception("stopped the incorrect job:{$this->currentJobID}, provided job id:{$data['jobID']}");
                }
                $this->finishRepeating();
                break;
            case MessageTypeEnum::LAST_MSG:
                if ($this->inRepeatingLoop()) {
                    $this->finishRepeating();
                    throw new ShutdownLoopException();
                }
                break;
        }
    }

    /**
     * @return bool
     */
    public function inRepeatingLoop(): bool
    {
        return $this->inRepeatingLoop;
    }

    /**
     * @return void
     */
    public function finishRepeating()
    {
        $this->inRepeatingLoop = false;
    }

    /**
     * 开始阻塞处理消息传输和处理,直至指定时间返回.
     *
     * @param float|int|null $interval 阻塞时间(秒). 不传代表永久阻塞.
     *
     * @throws
     */
    private function process(float $interval = null)
    {
        if ($interval !== null) {
            $this->processTimer = $this->eventLoop->addTimer($interval, function () {
                $this->eventLoop->stop();
                $this->processTimer = null;
            });
        }

        try {
            $this->eventLoop->run();
        } catch (\Throwable $e) {
            $this->removeProcessTimer();
            throw $e;
        }
    }

    /**
     * 移除事件循环的计时器.
     */
    private function removeProcessTimer()
    {
        if ($this->processTimer) {
            $this->eventLoop->cancelTimer($this->processTimer);
            $this->processTimer = null;
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