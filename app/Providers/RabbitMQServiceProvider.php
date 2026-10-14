<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class RabbitMQServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Only declare topology when a queue worker is actually about to consume
        // the queue, to avoid opening an AMQP connection on every artisan
        // command (migrate, tinker, test, etc).
        if ($this->app->runningInConsole()
            && config('queue.default') === 'rabbitmq'
            && $this->isRunningQueueWorkerCommand()
        ) {
            $this->declareTopology();
        }
    }

    /**
     * Determine whether the current console invocation is a queue worker command.
     */
    protected function isRunningQueueWorkerCommand(): bool
    {
        $command = $_SERVER['argv'][1] ?? null;

        return in_array($command, ['queue:work', 'queue:listen'], true);
    }

    /**
     * Declare RabbitMQ Primary & Dead-Letter Exchanges, Queues, and Bindings.
     */
    protected function declareTopology(): void
    {
        try {
            $host = config('queue.connections.rabbitmq.hosts.0.host');
            $port = config('queue.connections.rabbitmq.hosts.0.port');
            $user = config('queue.connections.rabbitmq.hosts.0.user');
            $pass = config('queue.connections.rabbitmq.hosts.0.password');
            $vhost = config('queue.connections.rabbitmq.hosts.0.vhost');

            $connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
            $channel = $connection->channel();

            // 1. Declare Main Exchange
            $mainExchange = config('queue.connections.rabbitmq.options.exchange.name', 'events');
            $channel->exchange_declare($mainExchange, 'direct', false, true, false);

            // 2. Declare Dead-Letter Exchange (DLX)
            $dlxExchange = config('queue.connections.rabbitmq.options.queue.arguments.x-dead-letter-exchange', 'events.dlx');
            $channel->exchange_declare($dlxExchange, 'direct', false, true, false);

            // 3. Declare Dead-Letter Queue (DLQ)
            $dlqRoutingKey = config('queue.connections.rabbitmq.options.queue.arguments.x-dead-letter-routing-key', 'order.reservations.dlq');
            $channel->queue_declare($dlqRoutingKey, false, true, false, false);
            $channel->queue_bind($dlqRoutingKey, $dlxExchange, $dlqRoutingKey);

            // 4. Declare Main Queue with DLX configuration
            $mainQueue = config('queue.connections.rabbitmq.options.queue.name', 'order.reservations');
            $messageTtl = config('queue.connections.rabbitmq.options.queue.arguments.x-message-ttl', 86400000);
            $args = new AMQPTable([
                'x-dead-letter-exchange' => $dlxExchange,
                'x-dead-letter-routing-key' => $dlqRoutingKey,
                'x-message-ttl' => $messageTtl,
            ]);

            $channel->queue_declare($mainQueue, false, true, false, false, false, $args);
            $channel->queue_bind($mainQueue, $mainExchange, $mainQueue);

            $channel->close();
            $connection->close();
        } catch (Throwable $e) {
            Log::warning('RabbitMQ Topology declaration skipped/failed: '.$e->getMessage());
        }
    }
}
