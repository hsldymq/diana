<?php

declare(strict_types=1);

namespace Archman\Diana;

use Archman\Whisper\AbstractWorker;
use Archman\Whisper\Interfaces\WorkerFactoryInterface;

class AgentFactory implements WorkerfactoryInterface
{
    /**
     * @var array [
     *      [$signal, $handler],
     *      ...
     * ]
     */
    private $signalHandlers = [];

    /**
     * @var array [
     *      [$event, $handler],
     *      ...
     * ]
     */
    private $eventHandlers = [];

    /**
     * @var null|int
     */
    private $idleWaitSec = null;

    /**
     * @var null|bool
     */
    private $isPassiveShutdown = null;

    /**
     * @var bool
     */
    private $captureSignal = true;

    public function makeWorker(string $id, $socketFD): AbstractWorker
    {
        $agent = new Agent($id, $socketFD);

        if ($this->captureSignal) {
            // 在以非daemon方式运行的情况下,子进程作为前台进程组会收到SIGINT,SIGQUIT而非正常退出,所以需要捕获该信号
            if (defined('SIGINT')) {
                $agent->addSignalHandler(SIGINT, function () {});
            }

            if (defined('SIGQUIT')) {
                $agent->addSignalHandler(SIGQUIT, function () {});
            }
        }

        foreach ($this->signalHandlers as $each) {
            $agent->addSignalHandler($each[0], $each[1]);
        }

        foreach ($this->eventHandlers as $each) {
            $agent->on($each[0], $each[1]);
        }

        if ($this->idleWaitSec !== null) {
            $agent->setIdleWait($this->idleWaitSec);
        }

        if ($this->isPassiveShutdown !== null) {
            $agent->setShutdownMode($this->isPassiveShutdown);
        }

        return $agent;
    }

    /**
     * 注册worker的信号处理器.
     *
     * @param int $signal
     * @param callable $handler
     *
     * @return self
     */
    public function registerSignal(int $signal, callable $handler): self
    {
        $this->signalHandlers[] = [$signal, $handler];

        return $this;
    }

    /**
     * 注册agent的事件
     *
     * @param string $event
     * @param callable $handler
     *
     * @return self
     */
    public function registerEvent(string $event, callable $handler): self
    {
        $this->eventHandlers[] = [$event, $handler];

        return $this;
    }

    /**
     * 设置agent空闲后等待一定时间后再退出,这样可以允许复用执行其他job.
     *
     * @param int $seconds 必须大于0,否则设置无效
     *
     * @return self
     */
    public function setIdleWait(int $seconds): self
    {
        if ($seconds > 0) {
            $this->idleWaitSec = $seconds;
        }

        return $this;
    }

    /**
     * 设置worker关闭为被动关闭模式还是主动关闭模式.
     *
     * 主动关闭是指worker进程结束事件循环,退出主逻辑最后脚本结束
     * 被动关闭是指worker告知dispatcher让其通过信号杀死自己
     *
     * 默认是主动关闭模式
     * 由于使用grpc 1.20以下版本的扩展时,fork的子进程无法正常结束,这里提供了一种被动关闭机制来防止这种情况方式.
     *
     * @param bool $isPassive
     *
     * @return self
     */
    public function setShutdownMode(bool $isPassive): self
    {
        $this->isPassiveShutdown = $isPassive;

        return $this;
    }

    /**
     * 默认情况下,worker进程会捕获SIGINT和SIGQUIT信号,防止被信号直接杀死.
     *
     * @param bool $c 传递false不默认捕获这两个信号
     *
     * @return self
     */
    public function setCaptureSignal(bool $c): self
    {
        $this->captureSignal = $c;

        return $this;
    }
}