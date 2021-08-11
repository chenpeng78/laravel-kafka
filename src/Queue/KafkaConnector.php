<?php

/**
 * Created by PhpStorm.
 * User: cp
 * Date: 2021/08/10
 * Time: 10:26
 */

namespace phpkafka\LaravelQueueKafka\Queue;

use Illuminate\Container\Container;
use Illuminate\Queue\Connectors\ConnectorInterface;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;

class KafkaConnector implements ConnectorInterface
{
    /**
     * @var Container
     */
    private $container;

    /**
     *
     * @param  Container  $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * 建立队列连接
     *
     * @param  array  $config
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {

        /** 初始化生产者 */
        $producerConf = $this->container->makeWith('queue.kafka.conf', []);
        $producerConf->set('bootstrap.servers', $config['brokers']);
        $producer = $this->container->makeWith('queue.kafka.producer', ['conf' => $producerConf]);
        $producer->addBrokers($config['brokers']);

        /** 初始化配置 */
        $conf = $this->container->makeWith('queue.kafka.conf', []);
        $conf->set('group.id', array_get($config, 'consumer_group_id', 'php-pubsub'));
        $conf->set('metadata.broker.list', $config['brokers']);
        $conf->set('enable.auto.commit', 'false');
        /**
         * earliest 当各分区下有已提交的offset时，从提交的offset开始消费；无提交的offset时，从头开始消费
         * latest  当各分区下有已提交的offset时，从提交的offset开始消费；无提交的offset时，消费新产生的该分区下的数据
         */
        $conf->set('auto.offset.reset', 'largest');

        /** 初始化消费者 */
        $consumer = $this->container->makeWith('queue.kafka.consumer', ['conf' => $conf]);

        return new KafkaQueue(
            $producer,
            $consumer,
            $config
        );
    }
}
