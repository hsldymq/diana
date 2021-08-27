<?php

declare(strict_types=1);

namespace Archman\Diana;

/**
 * Agent调度器.
 */
class AgentScheduler
{
    const IDLE = 0;

    const BUSY = 1;

    const RETIRED = 2;

    /**
     * @var array [
     *      self::IDLE => [
     *          $agentID => true,
     *          ...
     *      ],
     *      self::WORKING => [
     *          $agentID => true,
     *          ...
     *      ],
     *      self::RETIRED => [
     *          $agentID => true,
     *          ...
     *      ],
     * ]
     */
    private $agentList = [
        self::IDLE => [],
        self::BUSY => [],
        self::RETIRED => [],
    ];

    /**
     * @var array [
     *      $workerID => $status,
     * ]
     */
    private $agentState = [];

    /**
     * 将一个worker加入到调度器中.
     *
     * @param string $agentID
     * @param bool $allocated 是否已经被分配,true时设为busy
     */
    public function add(string $agentID, bool $allocated = false): void
    {
        if (isset($this->agentState[$agentID])) {
            return;
        }

        if ($allocated) {
            $this->agentList[self::BUSY][$agentID] = true;
            $this->agentState[$agentID] = self::BUSY;
        } else {
            $this->agentList[self::IDLE][$agentID] = true;
            $this->agentState[$agentID] = self::IDLE;
        }
    }

    /**
     * 从调度器中移除worker.
     *
     * @param string $agentID
     */
    public function remove(string $agentID): void
    {
        $state = $this->agentState[$agentID] ?? null;
        if ($state === null) {
            return;
        }

        unset($this->agentList[$state][$agentID]);
        unset($this->agentState[$agentID]);
    }

    /**
     * 将一个worker置为退休,该worker不再参与调度.
     *
     * @param string $agentID
     */
    public function retire(string $agentID): void
    {
        $state = $this->agentState[$agentID] ?? null;
        if ($state === null || $state === self::RETIRED) {
            return;
        }

        unset($this->agentList[$state][$agentID]);
        $this->agentList[self::RETIRED][$agentID] = true;
        $this->agentState[$agentID] = self::RETIRED;
    }

    /**
     * 分配一个可以用的agent.
     *
     * @return string|null 成功分配返回agent id, 没有可用的agent返回null
     */
    public function allocate(): ?string
    {
        end($this->agentList[self::IDLE]);
        $agentID = key($this->agentList[self::IDLE]);
        if ($agentID !== null) {
            unset($this->agentList[self::IDLE][$agentID]);
            $this->agentList[self::BUSY][$agentID] = true;
            $this->agentState[$agentID] = self::BUSY;
        }

        return $agentID;
    }

    /**
     * 归还一个agent.
     *
     * @param string $agentID
     */
    public function release(string $agentID): void
    {
        if (($this->agentState[$agentID] ?? null) !== self::BUSY) {
            return;
        }

        unset($this->agentList[self::BUSY][$agentID]);
        $this->agentList[self::IDLE][$agentID] = true;
        $this->agentState[$agentID] = self::IDLE;
    }
}