<?php

declare(strict_types=1);

use Benchmark\SymfonyAmqplibHelper;

require_once dirname(__DIR__) . '/vendor/autoload.php';
$bus = SymfonyAmqplibHelper::createMessageBus();
$envelope = new \Symfony\Component\Messenger\Envelope(new \stdClass());
for ($i = 0; $i < 100_000; $i++) {
    $bus->dispatch($envelope);
}
