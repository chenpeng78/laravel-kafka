<?php

/**
 * Created by PhpStorm.
 * User: cp
 * Date: 2021/08/10
 * Time: 10:26
 */

return [

    'driver' => 'kafka',

    /*
     * 默认队列的名称
     */
    'queue' => env('KAFKA_QUEUE', 'default'),

    /*
     * 默认消费者所在的组
     */
    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP_ID', 'laravel_queue'),

    /*
     * 地址配置，多个用","分割
     */
    'brokers' => env('KAFKA_BROKERS', 'localhost'),

    /*
     * 确定的秒数睡眠与卡夫卡交流如果有一个错误
     * 如果设置为false,它会抛出一个异常,而不是做X秒的睡眠
     */
    'sleep_on_error' => env('KAFKA_ERROR_SLEEP', 5),

    /*
     * 睡眠时检测到死锁
     */
    'sleep_on_deadlock' => env('KAFKA_DEADLOCK_SLEEP', 2),

];
