<?php

namespace Basis;

use Exception;
use Ramsey\Uuid\Uuid;

class Service
{
    private $app;
    private $fiber;
    private $queue;
    private $running = false;
    private $tube;

    public function __construct(Application $app, Queue $queue, Fiber $fiber, $tube)
    {
        $this->app = $app;
        $this->fiber = $fiber;
        $this->queue = $queue;
        $this->tube = $tube;

        $this->queue->init($tube);
    }

    public function send($nick, $arguments)
    {
        $this->queue->put('router', [
            'uuid' => Uuid::uuid4()->toString(),
            'tube' => $this->tube,
            'job' => 'router.process',
            'data' => [
                'job' => $nick,
                'data' => $arguments,
            ]
        ]);
    }

    public function process()
    {
        $task = $this->queue->take($this->tube);

        if(!$task) {
            return;
        }

        $task->ack();

        $params = $task->offsetExists('data') ? $task['data'] : [];

        try {
            $data = $this->app->dispatch($task['job'], $params);
            if($task->offsetExists('tube')) {
                $this->queue->put($task['tube'], [
                    'uuid' => $task['uuid'],
                    'data' => $data
                ]);
            }
        } catch(Exception $e) {
            if($task->offsetExists('tube')) {
                $this->queue->put($task['tube'], [
                    'uuid' => $task['uuid'],
                    'data' => $e->getMessage(),
                    'error' => true,
                ]);
            }
        }
    }

    public function getTube()
    {
        return $this->tube;
    }
}