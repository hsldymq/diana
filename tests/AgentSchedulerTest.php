<?php

use PHPUnit\Framework\TestCase;
use Archman\Diana\AgentScheduler;

class AgentSchedulerTest extends TestCase
{
    public function testAdd()
    {
        $scheduler1 = new AgentScheduler();

        $scheduler1->add('a');
        $this->assertCountWorker(1, 0, 0, $scheduler1);

        $scheduler1->add('b');
        $this->assertCountWorker(2, 0, 0, $scheduler1);

        // 重复增加没效果
        $scheduler1->add('a');
        $this->assertCountWorker(2, 0, 0, $scheduler1);

        $scheduler1->add('d', true);
        $this->assertCountWorker(2, 0, 1, $scheduler1);
    }

    /**
     * @depends testAdd
     */
    public function testRemove()
    {
        $scheduler = new AgentScheduler();

        $scheduler->add('a');
        $scheduler->add('b');

        $scheduler->remove('a');
        $this->assertCountWorker(1, 0, 0, $scheduler);

        // 重复移除无效果
        $scheduler->remove('a');
        $this->assertCountWorker(1, 0, 0, $scheduler);

        // 移除不存在的worker无效果
        $scheduler->remove('c');
        $this->assertCountWorker(1, 0, 0, $scheduler);

        $scheduler->remove('b');
        $this->assertCountWorker(0, 0, 0, $scheduler);
    }

    /**
     * @depends testAdd
     */
    public function testRetire()
    {
        $scheduler = new AgentScheduler();
        $scheduler->add('a');
        $scheduler->add('b');

        $scheduler->retire('a');
        $this->assertCountWorker(1, 1, 0, $scheduler);

        // 重复退休没效果
        $scheduler->retire('a');
        $scheduler->retire('a');
        $scheduler->retire('a');
        $this->assertCountWorker(1, 1, 0, $scheduler);

        // 退休不存在的worker没效果
        $scheduler->retire('c');
        $this->assertCountWorker(1, 1, 0, $scheduler);

        $scheduler->retire('b');
        $this->assertCountWorker(0, 2, 0, $scheduler);
    }

    /**
     * @depends testRemove
     * @depends testRetire
     */
    public function testCombineBasicOperations()
    {
        $scheduler = (new AgentScheduler());
        $scheduler->add('a');
        $scheduler->add('b');
        $scheduler->add('c');

        $scheduler->remove('b');
        $scheduler->remove('d');    // 不存在的worker
        $scheduler->retire('c');
        $scheduler->retire('e');    // 不存在的worker
        $scheduler->add('f');

        $this->assertCountWorker(2, 1, 0, $scheduler);
    }

    /**
     * @depends testCombineBasicOperations
     */
    public function testAllocate()
    {
        $scheduler = (new AgentScheduler());
        $scheduler->add('a');
        $scheduler->add('b');
        $scheduler->add('c');

        $this->assertEquals('c', $scheduler->allocate());
        $this->assertEquals('b', $scheduler->allocate());
        $this->assertEquals('a', $scheduler->allocate());
        $this->assertCountWorker(0, 0, 3, $scheduler);

        $this->assertNull($scheduler->allocate());
        $this->assertCountWorker(0, 0, 3, $scheduler);
    }

    /**
     * @depends testAllocate
     */
    public function testRelease()
    {
        $scheduler = (new AgentScheduler());
        $scheduler->add('a');
        $scheduler->add('b');
        $scheduler->add('c');
        $scheduler->add('d');
        $scheduler->add('e');

        $scheduler->allocate();     // e
        $scheduler->allocate();     // d
        $scheduler->allocate();     // c
        $scheduler->allocate();     // b
        $scheduler->allocate();     // a

        $scheduler->allocate();     // null

        $scheduler->release('c');
        $this->assertCountWorker(1, 0, 4, $scheduler);

        $scheduler->release('a');
        $this->assertCountWorker(2, 0, 3, $scheduler);

        $scheduler->retire('a');
        $scheduler->retire('d');

        $scheduler->release('b');
        $this->assertCountWorker(2, 2, 1, $scheduler);

        // a:r
        // b:i
        // c:i
        // d:r
        // e:b

        $scheduler->release('a');
        $scheduler->release('b');
        $scheduler->release('b');
        $this->assertCountWorker(2, 2, 1, $scheduler);
    }

    private function assertCountWorker(int $expectIdle, int $expectRetired, int $expectBusy, AgentScheduler $scheduler)
    {
        $this->assertEquals($expectIdle, (function () {return count($this->agentList[AgentScheduler::IDLE]);})->bindTo($scheduler, $scheduler)());
        $this->assertEquals($expectRetired, (function () {return count($this->agentList[AgentScheduler::RETIRED]);})->bindTo($scheduler, $scheduler)());
        $this->assertEquals($expectBusy, (function () {return count($this->agentList[AgentScheduler::BUSY]);})->bindTo($scheduler, $scheduler)());
    }
}