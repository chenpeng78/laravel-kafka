<?php

/**
 * Created by PhpStorm.
 * User: cp
 * Date: 2021/08/10
 * Time: 10:26
 */

namespace phpkafka\LaravelQueueKafka;

class LumenQueueKafkaServiceProvider extends LaravelQueueKafkaServiceProvider
{
    /**
     * 注册应用程序的事件监听器
     */
    public function boot()
    {
        parent::boot();
    }

}
