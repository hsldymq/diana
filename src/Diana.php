<?php

declare(strict_types=1);

namespace Archman\Diana;

use Archman\Diana\Timer\Duration;
use Archman\Diana\Timer\Timer;
use Archman\Diana\Timer\TimingInterface;
use Archman\Whisper\AbstractMaster;
use Archman\Whisper\Interfaces\WorkerFactoryInterface;
use Archman\Whisper\Message;

/**
 * 可以使用on方法监听以下预定义事件:
 * @event start                     dispatcher启动
 *                                  参数: \Archman\Diana\Diana $master
 *
 * @event patrolling                进行一次巡逻,巡逻会检查僵尸进程,并给使用者定时进行抽样的机会
 *                                  参数: \Archman\Diana\Diana $master
 *
 * @event agentExit                 agent子进程退出
 *                                  参数: string $agentID, int $pid, \Archman\Diana\Diana $master
 *
 * @event shutdown                  master进程退出
 *                                  参数: \Archman\Diana\Diana $master
 *
 * @event error                     出现错误
 *                                  参数: \Throwable $ex, \Archman\Diana\Diana $master
 */
class Diana extends AbstractMaster
{
    use TailingEventEmitterTrait;

    const STATE_RUNNING = 1;
    const STATE_SHUTTING = 2;
    const STATE_SHUTDOWN = 3;

    private $state = self::STATE_SHUTDOWN;

    /**
     * 进程id对agent id映射关系
     *
     * @var array
     * [
     *      $pid => $agentID,
     *      ...
     * ]
     */
    private $idMap = [];

    /**
     * @var array
     * [
     *      $agentID => [
     *          'jobID' => (string|null),
     *      ],
     * ]
     */
    private $agentInfo = [];

    /**
     * @var array [
     *      $jobID => [
     *          'jobObject' => (object),
     *          'agentID' => (string|null),
     *          'remove' => (bool|null)
     *      ],
     *      ...
     * ]
     */
    private $jobInfo = [];

    /**
     * @var float 进行一次巡逻的间隔周期(秒)
     */
    private $patrolPeriod = 300.0;

    /**
     * @var \Throwable
     */
    private $shutdownError;

    /**
     * @var AgentFactory
     */
    private $agentFactory;

    /**
     * @var AgentScheduler
     */
    private $agentScheduler;

    /**
     * @var Timer
     */
    private $timer;

    /**
     * @var int 关闭的超时时间,超过后主进程自行退出.
     */
    private $shutdownTimeoutSec = 30;

    public function __construct(WorkerFactoryInterface $agentFactory)
    {
        parent::__construct();

        $this->agentFactory = $agentFactory;
        $this->agentScheduler = new AgentScheduler();
        $this->timer = new Timer(Duration::SECOND, $this->getEventLoop(), function (string $jobID) {
            try {
                $this->tryAssign($jobID);
            } catch (\Throwable $e) {
                $this->errorlessEmit('error', [$e]);
            }
        });

        $this->on('__workerExit', function (string $agentID, int $pid) {
            $this->errorlessEmit('agentExit', [$agentID, $pid]);
            $this->clearAgent($agentID, $pid);
        });
    }

    public function run(bool $daemonize = false)
    {
        if ($this->state !== self::STATE_SHUTDOWN) {
            return;
        }

        if ($daemonize) {
            $this->daemonize();
        }

        $this->state = self::STATE_RUNNING;
        $this->errorlessEmit('start');
        $this->timer->start();

        while ($this->state === self::STATE_RUNNING) {
            try {
                $this->process($this->patrolPeriod);
            } catch (\Throwable $e) {
                $this->shutdown($e);
                break;
            } finally {
                // 补杀僵尸进程
                $this->waitChildren();
            }

            $this->errorlessEmit('patrolling');
        }

        $this->timer->clearJobs();
        try {
            // 使所有worker都退出
            $this->informAgentsQuit();
        } catch (\Throwable $e) {
            $this->shutdownError = $this->shutdownError ?: $e;
        } finally {
            $this->errorlessEmit('shutdown');
            $this->state = self::STATE_SHUTDOWN;
        }

        if ($this->shutdownError) {
            throw $this->shutdownError;
        }
    }

    /**
     * 添加任务.
     *
     * @param string $jobID
     * @param JobInterface $job
     * @param TimingInterface $timing
     *
     * @return self
     */
    public function addJob(string $jobID, JobInterface $job, TimingInterface $timing): self
    {
        $this->jobInfo[$jobID]['jobObject'] = $job;
        $this->jobInfo[$jobID]['agentID'] = $this->jobInfo[$jobID]['agentID'] ?? null;
        $this->timer->addJob($jobID, $timing);
        unset($this->jobInfo[$jobID]['remove']);

        return $this;
    }

    /**
     * 移除任务.
     *
     * 如果任务尚未执行,则直接移除任务.
     * 如果任务正在执行中,打上移除标记,等任务执行完成后移除.
     *
     * @param string $jobID
     */
    public function removeJob(string $jobID)
    {
        $this->timer->cancelJob($jobID);
        if (!isset($this->jobInfo[$jobID])) {
            return;
        }

        if ($this->jobInfo[$jobID]['agentID'] ?? false) {
            $this->jobInfo[$jobID]['remove'] = true;
        }

        $this->doRemoveJob($jobID);
    }

    /**
     * @param string $agentID
     * @param Message $message
     *
     * @throws
     */
    public function onMessage(string $agentID, Message $message)
    {
        $type = $message->getType();

        switch ($type) {
            case MessageTypeEnum::JOB_FINISHED:
                $this->agentScheduler->release($agentID);
                $jobID = $this->agentInfo[$agentID]['jobID'] ?? '';
                if ($jobID) {
                    $this->jobInfo[$jobID]['agentID'] = null;
                    $this->agentInfo[$agentID]['jobID'] = null;
                }
                if ($this->jobInfo[$jobID]['remove'] ?? false) {
                    $this->doRemoveJob($jobID);
                }
                $this->timer->finish($jobID);
                break;
            case MessageTypeEnum::STOP_SENDING:
                // 子进程agent主动告知不再希望收到更多队列消息
                // 这时会启动agent关闭沟通流程
                $this->agentScheduler->retire($agentID);
                try {
                    $this->sendLastMessage($agentID);
                } catch (\Throwable $e) {
                    $this->errorlessEmit('error', [$e]);
                }
                break;
            case MessageTypeEnum::KILL_ME:
                // 对于被动关闭模式,子进程agent收到LAST_MSG,会返回KILL_ME消息让主进程杀死自己
                // 对于grpc扩展 1.20以下的版本,fork出的子进程无法正常退出,只有通过信号来杀死
                // 并且无法保证其他扩展是否也有这个问题, 这是这种模式存在的原因.
                // 当主进程收到KILL_ME消息代表了子进程已经做完了所有工作,所以杀死进程是安全的.
                $this->killWorker($agentID, SIGKILL);
                break;
            default:
                $this->errorlessEmit('message', [$agentID, $message]);
        }
    }

    /**
     * 触发事件.
     *
     * 屏蔽掉事件handler引发的错误.
     *
     * @param string $event
     * @param array $args
     */
    public function errorlessEmit(string $event, array $args = [])
    {
        try {
            $this->emit($event, $args);
        } finally {}
    }

    /**
     * @param \Throwable|null $withError
     */
    public function shutdown(\Throwable $withError = null)
    {
        if ($this->state !== self::STATE_RUNNING) {
            return;
        }
        if ($withError && !$this->shutdownError) {
            $this->shutdownError = $withError;
        }

        $this->stopProcess();
        $this->state = self::STATE_SHUTTING;
    }

    /**
     * 设置退出超时时间.
     *
     * @param int $sec
     *
     * @return self
     */
    public function setShutdownTimeoutSec(int $sec): self
    {
        $this->shutdownTimeoutSec = $sec;

        return $this;
    }

    /**
     * @param string $jobID
     *
     * @return void
     * @throws \Throwable
     */
    private function tryAssign(string $jobID)
    {
        if (!isset($this->jobInfo[$jobID]) || $this->jobInfo[$jobID]['agentID']) {
            return;
        }

        $agentID = $this->scheduleAgent();

        try {
            $message = new Message(MessageTypeEnum::NORMAL_JOB, json_encode([
                'jobID' => $jobID,
                'job' => serialize($this->jobInfo[$jobID]['jobObject']),
            ]));
            $this->sendMessage($agentID, $message);
        } catch (\Throwable $e) {
            $this->agentScheduler->release($agentID);
            $this->timer->finish($jobID);
            throw $e;
        }
        $this->agentInfo[$agentID]['jobID'] = $jobID;
        $this->jobInfo[$jobID]['agentID'] = $agentID;
    }

    /**
     * 安排一个agent,如果没有空闲agent,创建一个.
     *
     * @return string agent id
     * @throws
     */
    private function scheduleAgent(): string
    {
        while (($agentID = $this->agentScheduler->allocate()) !== null) {
            $c = $this->getCommunicator($agentID);
            if ($c && $c->isWritable()) {
                break;
            }
        }

        if (!$agentID) {
            try {
                $agentID = $this->createWorker($this->agentFactory);
            } catch (\Throwable $e) {
                $this->errorlessEmit('error', [$e]);
                throw $e;
            }

            $pid = $this->getWorkerPID($agentID);
            $this->agentScheduler->add($agentID, true);
            $this->idMap[$pid] = $agentID;
            $this->agentInfo[$agentID] = [
                'jobID' => null,
            ];
        }

        return $agentID;
    }

    /**
     * @param string $agentID
     *
     * @throws
     */
    private function sendLastMessage(string $agentID)
    {
        $this->sendMessage($agentID, new Message(MessageTypeEnum::LAST_MSG, ''));
    }

    /**
     * 清理退出的子进程.
     */
    private function waitChildren()
    {
        while (($pid = pcntl_wait($status, WNOHANG)) > 0) {
            $this->clearAgent($this->idMap[$pid] ?? '', $pid);
        }
    }

    /**
     * 通知并确保所有worker退出.
     *
     * @throws
     */
    private function informAgentsQuit()
    {
        $startInformTime = $now = time();
        $informCount = 0;
        do {
            // 每10秒进行一次通知
            if (($now - $startInformTime) / 10 >= $informCount) {
                $informCount++;
                foreach ($this->agentInfo as $agentID => $each) {
                    try {
                        $this->sendLastMessage($agentID);
                    } catch (\Throwable $e) {
                        $this->errorlessEmit('error', [$e]);
                    }
                }
            }

            if ($this->countWorkers() === 0) {
                break;
            }

            $this->process(0.5);
            $this->waitChildren();
            $now = time();
            // 防止子进程无响应,这里循环一定时间后直接退出
        } while (($now - $startInformTime) < $this->shutdownTimeoutSec);
    }

    /**
     * 清理Agent信息.
     *
     * @param string $agentID
     * @param int $pid
     */
    private function clearAgent(string $agentID, int $pid)
    {
        $jobID = $this->agentInfo[$agentID]['jobID'] ?? '';
        if ($jobID) {
            $this->timer->finish($jobID);
            if (isset($this->jobInfo[$jobID])) {
                $this->jobInfo[$jobID]['agentID'] = null;
            }
        }

        $this->agentScheduler->remove($agentID);
        unset($this->agentInfo[$agentID]);
        unset($this->idMap[$pid]);
    }

    private function doRemoveJob(string $jobID)
    {
        unset($this->jobInfo[$jobID]);
    }
}