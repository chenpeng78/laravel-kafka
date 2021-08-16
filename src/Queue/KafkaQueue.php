<?php

/**
 * Created by PhpStorm.
 * User: cp
 * Date: 2021/08/10
 * Time: 10:26
 */

namespace phpkafka\LaravelQueueKafka\Queue;

use ErrorException;
use Exception;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Log;
use phpkafka\LaravelQueueKafka\Exceptions\QueueKafkaException;
use phpkafka\LaravelQueueKafka\Queue\Jobs\KafkaJob;

class KafkaQueue extends Queue implements QueueContract
{
    /**
     * @var string
     */
    protected $defaultQueue;

    /**
     * @var int
     */
    protected $sleepOnError;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    private $correlationId;

    /**
     * @var \RdKafka\Producer
     */
    private $producer;

    /**
     * @var \RdKafka\KafkaConsumer
     */
    private $consumer;

    /**
     * @var array
     */
    private $subscribedQueueNames = [];

    /**
     * @param \RdKafka\Producer $producer
     * @param \RdKafka\KafkaConsumer $consumer
     * @param array $config
     */
    public function __construct(\RdKafka\Producer $producer, \RdKafka\KafkaConsumer $consumer, $config)
    {
        $this->defaultQueue = $config['queue'];
        $this->sleepOnError = isset($config['sleep_on_error']) ? $config['sleep_on_error'] : 5;
        $this->producer = $producer;
        $this->consumer = $consumer;
        $this->config = $config;
    }

    /**
     * 获取队列的大小
     *
     * @param string $queue
     *
     * @return int
     */
    public function size($queue = null)
    {
        //由于kafka是无限队列我们不能计算队列的大小
        return 1;
    }

    /**
     * 推送一个新工作在队列中
     *
     * @param string $job
     * @param mixed $data
     * @param string $queue
     *
     * @return bool
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, []);
    }

    /**
     * 把原始任务压入队列
     *
     * @param string $payload
     * @param string $queue
     * @param array $options
     *
     * @throws QueueKafkaException
     *
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        try {
            $topic = $this->getTopic($queue);

            $pushRawCorrelationId = $this->getCorrelationId();

            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $payload, $pushRawCorrelationId);
            $this->producer->flush(-1);
            return $pushRawCorrelationId;
        } catch (ErrorException $exception) {
            $this->reportConnectionError('pushRaw', $exception);
        }
    }

    /**
     * 推动新工作后到队列延迟
     *
     * @param \DateTime|int $delay
     * @param string $job
     * @param mixed $data
     * @param string $queue
     *
     * @throws QueueKafkaException
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        //延迟队列,没有实现
        throw new QueueKafkaException('kafka 延迟队列未实现');
    }

    /**
     *  获取队列的下一份工作了
     *
     * @param string|null $queue
     *
     * @throws QueueKafkaException
     *
     * @return \Illuminate\Queue\Jobs\Job|null
     */
    public function pop($queue = null)
    {
        try {
            $queue = $this->getQueueName($queue);
            if (!in_array($queue, $this->subscribedQueueNames)) {
                $this->subscribedQueueNames[] = $queue;
                $this->consumer->subscribe($this->subscribedQueueNames);
            }
            //消费消息并触发回调,超时（毫秒）
            $message = $this->consumer->consume(30 * 1000);
            if ($message === null) {
                return null;
            }
            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    return new KafkaJob(
                        $this->container, $this, $message,
                        $this->connectionName, $queue ?: $this->defaultQueue
                    );
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    break;
                default:
                    throw new QueueKafkaException($message->errstr(), $message->err);
            }
        } catch (\RdKafka\Exception $exception) {
            throw new QueueKafkaException('不能从队列中获取任务', 0, $exception);
        }
    }

    /**
     * @param string $queue
     *
     * @return string
     */
    private function getQueueName($queue)
    {
        return $queue ?: $this->defaultQueue;
    }

    /**
     * 获取一个kafka主题
     *
     * @param $queue
     *
     * @return \RdKafka\ProducerTopic
     */
    private function getTopic($queue)
    {
        return $this->producer->newTopic($this->getQueueName($queue));
    }

    /**
     * 集相关id的消息公布
     *
     * @param string $id
     */
    public function setCorrelationId($id)
    {
        $this->correlationId = $id;
    }

    /**
     * 检索相关id,或者一个惟一的id
     *
     * @return string
     */
    public function getCorrelationId()
    {
        return $this->correlationId ?: uniqid('', true);
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * 创建一个从给定的工作负载阵列和数据
     *
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     *
     * @return array
     */
    protected function createPayloadArray($job, $data = '', $queue = null)
    {
        return array_merge(parent::createPayloadArray($job, $data), [
            'id' => $this->getCorrelationId(),
            'attempts' => 0,
        ]);
    }

    /**
     * @param string $action
     * @param Exception $e
     *
     * @throws QueueKafkaException
     */
    protected function reportConnectionError($action, Exception $e)
    {
        Log::error('Kafka error while attempting ' . $action . ': ' . $e->getMessage());

        // 如果设置为false,抛出一个错误,而不是等待
        if ($this->sleepOnError === false) {
            throw new QueueKafkaException('Error writing data to the connection with Kafka');
        }

        // 睡眠
        sleep($this->sleepOnError);
    }

    /**
     * 获取消费者
     *
     * @return \RdKafka\KafkaConsumer
     */
    public function getConsumer()
    {
        return $this->consumer;
    }

}
