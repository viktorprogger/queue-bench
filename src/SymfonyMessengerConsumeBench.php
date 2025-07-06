<?php

declare(strict_types=1);

namespace Benchmark;

use PhpBench\Attributes\BeforeClassMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Worker;

#[BeforeClassMethods('cleanupQueue')]
final class SymfonyMessengerConsumeBench
{
    private Worker $worker;
    private MessageBusInterface $bus;

    public function __construct()
    {
        $transport = SymfonyAmqplibHelper::createTransport();
        $this->bus = SymfonyAmqplibHelper::createMessageBus();

        $handler = new class {
            public function __invoke(TestMessage $message): void
            {
                // No-op
            }
        };

        $consumerBus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                TestMessage::class => [$handler],
            ])),
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(Settings::CONSUME_MESSAGE_COUNT));

        $this->worker = new Worker([$transport], $consumerBus, $dispatcher);
    }

    /**
     * How fast we can consume 10_000 messages
     */
    #[Iterations(5)]
    #[Revs(1)]
    #[BeforeMethods('pushMessagesForConsume')]
    public function benchConsume(): void
    {
        $this->worker->run();
    }

    public function pushMessagesForConsume(): void
    {
        $message = new TestMessage('test');
        $envelope = new Envelope($message);

        for ($i = 0; $i < Settings::CONSUME_MESSAGE_COUNT; $i++) {
            $this->bus->dispatch($envelope);
        }
    }

    public static function cleanupQueue(): void
    {
        // Create the transport using our helper
        $transport = SymfonyAmqplibHelper::createTransport();

        // Get the channel and purge the queue
        $connection = $transport->getConnection();
        $connection->setUp();
        $channel = $connection->channel();
        $channel->queue_purge('messages');
    }
}

class TestMessage
{
    public function __construct(public string $payload)
    {
    }
}
