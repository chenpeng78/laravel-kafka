<?php

/**
 * Created by PhpStorm.
 * User: cp
 * Date: 2021/08/10
 * Time: 10:26
 */

namespace phpkafka\LaravelQueueKafka\Queue\Jobs;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Database\DetectsDeadlocks;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\Jobs\JobName;
use Illuminate\Support\Str;
use phpkafka\LaravelQueueKafka\Exceptions\QueueKafkaException;
use phpkafka\LaravelQueueKafka\Queue\KafkaQueue;
use RdKafka\Message;

class KafkaJob extends Job implements JobContract
{
    use DetectsDeadlocks;

    /**
     * @var KafkaQueue
     */
    protected $connection;
    
    /**
     * @var KafkaQueue
     */
    protected $queue;

    /**
     * @var Message
     */
    protected $message;

    /**
     *
     * @param  Container  $container
     * @param  KafkaQueue  $connection
     * @param  Message  $message
     * @param $connectionName
     * @param $queue
     */
    public function __construct(Container $container, KafkaQueue $connection, Message $message, $connectionName, $queue)
    {
        $this->container = $container;
        $this->connection = $connection;
        $this->message = $message;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
    }

    /**
     * Fire the job
     *
     * @throws Exception
     */
    public function fire()
    {
        try {
            $payload = $this->payload();
            list($class, $method) = JobName::parse($payload['job']);

            with($this->instance = $this->resolve($class))->{$method}($this, $payload['data']);
        } catch (Exception $exception) {
            if (
                $this->causedByDeadlock($exception) ||
                Str::contains($exception->getMessage(), ['detected deadlock'])
            ) {
                sleep($this->connection->getConfig()['sleep_on_deadlock']);
                $this->fire();

                return;
            }

            throw $exception;
        }
    }

    /**
     * 获得一个任务
     *
     * @return int
     */
    public function attempts()
    {
        return (int) ($this->payload()['attempts']) + 1;
    }

    /**
     * 获取消息payload
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->message->payload;
    }

    /**
     * 从队列中删除工作
     */
    public function delete()
    {
        try {
            parent::delete();
            $this->connection->getConsumer()->commitAsync($this->message);
        } catch (\RdKafka\Exception $exception) {
            throw new QueueKafkaException('Could not delete job from the queue', 0, $exception);
        }
    }

    /**
     * 释放工作回到队列中
     *
     * @param  int  $delay
     *
     * @throws Exception
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $this->delete();

        $body = $this->payload();

        if (isset($body['data']['command']) === true) {
            $job = $this->unserialize($body);
        } else {
            $job = $this->getName();
        }

        $data = $body['data'];

        if ($delay > 0) {
            $this->connection->later($delay, $job, $data, $this->getQueue());
        } else {
            $this->connection->push($job, $data, $this->getQueue());
        }
    }

    /**
     * 设置队列ID
     *
     * @param  string  $id
     */
    public function setJobId($id)
    {
        $this->connection->setCorrelationId($id);
    }

    /**
     * 得到消息队列ID
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->message->key;
    }

    /**
     * 反序列化队列
     *
     * @param  array  $body
     *
     * @return mixed
     * @throws Exception
     *
     */
    private function unserialize(array $body)
    {
        try {
            return unserialize($body['data']['command']);
        } catch (Exception $exception) {
            if (
                $this->causedByDeadlock($exception)
                || Str::contains($exception->getMessage(), ['detected deadlock'])
            ) {
                sleep($this->connection->getConfig()['sleep_on_deadlock']);

                return $this->unserialize($body);
            }

            throw $exception;
        }
    }
}
