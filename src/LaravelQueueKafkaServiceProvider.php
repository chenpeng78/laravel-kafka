<?php

/**
 * Created by PhpStorm.
 * User: cp
 * Date: 2021/08/10
 * Time: 10:26
 */

namespace phpkafka\LaravelQueueKafka;

use Illuminate\Support\ServiceProvider;
use phpkafka\LaravelQueueKafka\Queue\KafkaConnector;

class LaravelQueueKafkaServiceProvider extends ServiceProvider
{

    /**
     * 注册服务提供者。
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/kafka.php', 'queue.connections.kafka'
        );

        $this->registerDependencies();
    }

    /**
     * 注册应用程序的事件监听器
     */
    public function boot()
    {

        $queue = $this->app['queue'];
        $connector = new KafkaConnector($this->app);

        $queue->addConnector('kafka', function () use ($connector) {
            return $connector;
        });
    }

    /**
     * 适配器注册依赖项的容器
     */
    protected function registerDependencies()
    {

        $this->app->bind('queue.kafka.conf', function () {
            return new \RdKafka\Conf();
        });

        $this->app->bind('queue.kafka.producer', function ($app,$parameters) {
            return new \RdKafka\Producer($parameters['conf']);
        });

        $this->app->bind('queue.kafka.consumer', function ($app, $parameters) {
            return new \RdKafka\KafkaConsumer($parameters['conf']);
        });

    }

    /**
     * 提供的服务提供者
     *
     * @return array
     */
    public function provides()
    {
        return [
            'queue.kafka.producer',
            'queue.kafka.consumer',
            'queue.kafka.conf',
        ];
    }
}
