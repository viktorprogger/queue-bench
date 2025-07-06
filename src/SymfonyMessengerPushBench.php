<?php

declare(strict_types=1);

namespace Benchmark;

use Jwage\PhpAmqpLibMessengerBundle\Batch;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputMode;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;


final class SymfonyMessengerPushBench
{
    private const MESSAGE_COUNT = 10_000;

    private MessageBusInterface $bus;
    private Batch $batchBus;

    public function __construct()
    {
        $this->bus = SymfonyAmqplibHelper::createMessageBus();
        $this->batchBus = new Batch($this->bus, 100);
    }

    /**
     * How fast we can push 1 message
     */
    #[Iterations(5)]
    #[Revs(self::MESSAGE_COUNT)]
    #[Warmup(1)]
    #[BeforeMethods('cleanupQueue')]
    #[AfterMethods('cleanupQueue')]
    #[OutputMode('throughput')]
    #[OutputTimeUnit('seconds')]
    public function benchPush(): void
    {
        $this->bus->dispatch(new Envelope(new TestMessage('test')));
    }

    /**
     * How fast we can push 100 messages
     */
    #[Iterations(5)]
    #[Revs(100)]
    #[Warmup(1)]
    #[BeforeMethods('cleanupQueue')]
    #[AfterMethods('cleanupQueue')]
    #[OutputMode('throughput')]
    #[OutputTimeUnit('seconds')]
    public function benchPushBatch(): void
    {
        $message = new TestMessage('test');
        $envelope = new Envelope($message);

        for ($i = 0; $i < 100; $i++) {
            $this->batchBus->dispatch($envelope);
        }

        $this->batchBus->flush();
    }

    public function cleanupQueue(): void
    {
        $transport = SymfonyAmqplibHelper::createTransport();
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
