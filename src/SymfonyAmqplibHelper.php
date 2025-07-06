<?php

declare(strict_types=1);

namespace Benchmark;

use Jwage\PhpAmqpLibMessengerBundle\Transport\AmqpSender;
use Jwage\PhpAmqpLibMessengerBundle\Transport\AmqpReceiver;
use Jwage\PhpAmqpLibMessengerBundle\Transport\AmqpTransport;
use Jwage\PhpAmqpLibMessengerBundle\Transport\AmqpTransportFactory;
use Jwage\PhpAmqpLibMessengerBundle\Transport\ConnectionFactory;
use Jwage\PhpAmqpLibMessengerBundle\Transport\AmqpConnectionFactory;
use Jwage\PhpAmqpLibMessengerBundle\Transport\DsnParser;
use Jwage\PhpAmqpLibMessengerBundle\RetryFactory;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Yiisoft\Test\Support\Container\SimpleContainer;

/**
 * Helper class to create AmqpTransport, Sender, and Receiver instances for benchmarking
 */
final class SymfonyAmqplibHelper
{
    /**
     * Creates an AmqpTransport instance
     */
    public static function createTransport(): AmqpTransport
    {
        // Create a ConnectionFactory with all required dependencies
        $connectionFactory = new ConnectionFactory(
            new DsnParser(),
            new RetryFactory(),
            new AmqpConnectionFactory(),
            null // no logger
        );
        
        // Create an AmqpTransportFactory
        $transportFactory = new AmqpTransportFactory($connectionFactory);
        
        // Get DSN from environment variables
        $dsn = sprintf(
            'phpamqplib://%s:%s@%s:%s/%%2F/messages',
            getenv('RABBITMQ_USER'),
            getenv('RABBITMQ_PASSWORD'),
            getenv('RABBITMQ_HOST'),
            getenv('RABBITMQ_PORT')
        );
        
        // Transport options
        $options = [
            'exchange' => [
                'name' => 'messages',
                'type' => 'direct',
                'default_publish_routing_key' => 'messages',
                'durable' => false,
            ],
            'queues' => [
                'messages' => [
                    'binding_keys' => ['messages'],
                ],
                'durable' => false,
            ],
        ];
        
        // Create and return the transport
        return $transportFactory->createTransport(
            $dsn,
            $options,
            new PhpSerializer()
        );
    }
    
    /**
     * Creates an AmqpSender instance
     */
    public static function createSender(): AmqpSender
    {
        $transport = self::createTransport();
        
        // Setup the exchange and queue
        $transport->setup();
        
        // The transport doesn't directly expose getSender, but AmqpTransport itself implements TransportInterface
        // So we can use it directly for sending
        return new AmqpSender(
            $transport->getConnection(),
            new PhpSerializer()
        );
    }
    
    /**
     * Creates an AmqpReceiver instance
     */
    public static function createReceiver(): AmqpReceiver
    {
        $transport = self::createTransport();
        
        // Setup the exchange and queue
        $transport->setup();
        
        // The transport doesn't directly expose getReceiver, but we can create one
        return new AmqpReceiver(
            $transport->getConnection(),
            new PhpSerializer()
        );
    }

    /**
     * Creates a MessageBus instance
     */
    public static function createMessageBus(): MessageBus
    {
        $transport = self::createTransport();

        $sendersLocator = new SendersLocator(
            [
                '*' => ['default-sender'],
            ],
            new SimpleContainer(['default-sender' => $transport]),
        );

        $messageBus = new MessageBus([
            new SendMessageMiddleware($sendersLocator),
            new HandleMessageMiddleware(new HandlersLocator(['*' => [static fn() => true]]), true),
        ]);

        return $messageBus;
    }
}
