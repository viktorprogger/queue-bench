<?php

declare(strict_types=1);

namespace Benchmark;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpBench\Attributes\BeforeClassMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use Psr\Log\NullLogger;
use Yiisoft\Injector\Injector;
use Yiisoft\Queue\AMQP\Adapter;
use Yiisoft\Queue\AMQP\QueueProvider;
use Yiisoft\Queue\AMQP\Settings\Exchange as ExchangeSettings;
use Yiisoft\Queue\AMQP\Settings\Queue as QueueSettings;
use Yiisoft\Queue\Cli\SimpleLoop;
use Yiisoft\Queue\Message\JsonMessageSerializer;
use Yiisoft\Queue\Message\Message;
use Yiisoft\Queue\Middleware\CallableFactory;
use Yiisoft\Queue\Middleware\Consume\ConsumeMiddlewareDispatcher;
use Yiisoft\Queue\Middleware\Consume\MiddlewareFactoryConsume;
use Yiisoft\Queue\Middleware\FailureHandling\FailureMiddlewareDispatcher;
use Yiisoft\Queue\Middleware\FailureHandling\MiddlewareFactoryFailure;
use Yiisoft\Queue\Middleware\Push\MiddlewareFactoryPush;
use Yiisoft\Queue\Middleware\Push\PushMiddlewareDispatcher;
use Yiisoft\Queue\Queue;
use Yiisoft\Queue\Worker\Worker;
use Yiisoft\Test\Support\Container\SimpleContainer;

#[BeforeClassMethods('cleanupQueue')]
final class YiisoftQueueConsumeBench
{
    private Queue $queue;

    public function __construct()
    {
        $this->queue = self::getQueue();
    }

    /**
     * How fast we can consume 10_000 messages
     */
    #[Iterations(5)]
    #[Revs(1)]
    #[BeforeMethods('pushMessagesForConsume')]
    public function benchConsume(): void
    {
        $this->queue->run();
    }

    public function pushMessagesForConsume(): void
    {
        $message = new Message('bench', ['payload' => 'test']);
        for ($i = 0; $i < Settings::CONSUME_MESSAGE_COUNT; $i++) {
            $this->queue->push($message);
        }
    }

    public static function cleanupQueue(): void
    {
        self::getQueue()->run();
    }

    private static function getQueue(): Queue
    {
        $loop = new SimpleLoop();
        $serializer = new JsonMessageSerializer();
        $queueProvider = new QueueProvider(
            new AMQPStreamConnection(
                getenv('RABBITMQ_HOST'),
                getenv('RABBITMQ_PORT'),
                getenv('RABBITMQ_USER'),
                getenv('RABBITMQ_PASSWORD'),
            ),
            new QueueSettings(durable: true),
            new ExchangeSettings(exchangeName: 'yiisoft', durable: true),
        )->withMessageProperties(['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $adapter = new Adapter($queueProvider, $serializer, $loop);

        $container = new SimpleContainer();
        $injector = new Injector($container);
        $callableFactory = new CallableFactory($container);
        $pushDispatcher = new PushMiddlewareDispatcher(new MiddlewareFactoryPush($container, $callableFactory));
        $consumeDispatcher = new ConsumeMiddlewareDispatcher(new MiddlewareFactoryConsume($container, $callableFactory));
        $failureDispatcher = new FailureMiddlewareDispatcher(new MiddlewareFactoryFailure($container, $callableFactory), []);
        $logger = new NullLogger();
        $worker = new Worker(
            ['bench' => static fn() => true],
            $logger,
            $injector,
            $container,
            $consumeDispatcher,
            $failureDispatcher
        );

        return new Queue($worker, $loop, $logger, $pushDispatcher, $adapter);
    }
}
