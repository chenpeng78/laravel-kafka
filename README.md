Laravel Kafka队列

======================

#### 安装


1. 安装librdkafka (https://github.com/edenhill/librdkafka)

    ```bash
    $ cd /tmp
    $ mkdir librdkafka
    $ cd librdkafka
    $ git clone https://github.com/edenhill/librdkafka.git .
    $ ./configure
    $ make
    $ make install
    ```
2. 安装  [php-rdkafka](https://github.com/arnaud-lb/php-rdkafka) PECL extension

    ```bash
    $ pecl install rdkafka
    ```
    
3. 配置 php-rdkafka 扩展
    `extension=rdkafka.so`
    
   b. 检车 rdkafka 是否安装成功  
   
       注意：如果你想在 php-fpm 上运行它，请先重启你的 php-fpm  
        
       php -i | grep rdkafka
   
   你的输出应该是这样的
   
       rdkafka
       rdkafka support => enabled
       librdkafka version (runtime) => 1.6.1-38-g7f0929-dirty
       librdkafka version (build) => 1.6.1.255

    
4. 通过 Composer 安装这个包

	    composer require phpkafka/laravel-kafka

5. 将 LaravelQueueKafkaServiceProvider 添加到providers数组中config/app.php

	    phpkafka\LaravelQueueKafka\LaravelQueueKafkaServiceProvider::class,
	
   如果您使用 Lumen，请将其放入 bootstrap/app.php
    
        $app->register(phpkafka\LaravelQueueKafka\LumenQueueKafkaServiceProvider::class);

6. 将这些属性添加到.env 文件中
        
		QUEUE_CONNECTION=kafka
		
		选择配置
		KAFKA_BROKERS=127.0.0.1:9092 #kafka地址，多个用,隔开
		KAFKA_ERROR_SLEEP=5 #确定的秒数睡眠与kafka交流如果有一个错误(秒)
		KAFKA_DEADLOCK_SLEEP=2 #睡眠时检测到死锁(秒)
		KAFKA_QUEUE=default #默认队列名
		KAFKA_CONSUMER_GROUP_ID=test  #默认分组

7. 如果你想为特定的消费者Group运行队列
        
        export KAFKA_CONSUMER_GROUP_ID="testgroup" && php artisan queue:work --sleep=3 --tries=3
8. 多消费者
	1629095586676.jpg![1629095586676](https://user-images.githubusercontent.com/9024302/129521025-59821ce5-2d4b-43f1-871c-88dc9f207cee.jpg)
      
   
