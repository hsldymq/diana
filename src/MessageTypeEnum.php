<?php

declare(strict_types=1);

namespace Archman\Diana;

class MessageTypeEnum
{
    public const NORMAL_JOB = 0;

    public const CUSTOM_JOB = 1;

    // JOB_FINISHED 告知Diana job执行完成
    public const JOB_FINISHED = 2;

    // Agent通知Diana不再分配任务,即将退出
    public const STOP_SENDING = 3;

    // Diana告知Agent后续不会再发送消息
    public const LAST_MSG = 4;

    // 在被动模式下子进程告知主进程发送kill信号杀死子进程
    public const KILL_ME = 5;

    // 通知agent停止继续执行job
    public const STOP_EXECUTING = 6;
}