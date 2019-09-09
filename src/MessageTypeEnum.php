<?php

declare(strict_types=1);

namespace Archman\Diana;

class MessageTypeEnum
{
    const NORMAL_JOB = 0;

    const CUSTOM_JOB = 1;

    // JOB_FINISHED 告知Diana job执行完成
    const JOB_FINISHED = 2;

    // Agent通知Diana不再分配任务,即将退出
    const QUITING = 3;

    // Diana告知Agent后续不会再发送消息
    const LAST_MSG = 4;

    // 在被动模式下子进程告知主进程发送kill信号杀死子进程
    const KILL_ME = 5;
}