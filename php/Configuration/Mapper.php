<?php

namespace Basis\Configuration;

use Basis\Application;
use Basis\Container;
use Basis\Registry;
use Basis\Toolkit;
use Tarantool\Client\Client;
use Tarantool\Mapper\Entity;
use Tarantool\Mapper\Mapper as TarantoolMapper;
use Tarantool\Mapper\Plugin;
use Tarantool\Mapper\Plugin\Annotation;
use Tarantool\Mapper\Plugin\Procedure;
use Tarantool\Mapper\Plugin\Sequence;
use Tarantool\Mapper\Plugin\Spy;
use Tarantool\Mapper\Plugin\Temporal;
use Tarantool\Mapper\Repository;
use Tarantool\Mapper\Schema;
use ReflectionProperty;

class Mapper
{
    use Toolkit;

    public function init(Container $container, Registry $registry)
    {
        foreach ($registry->listClasses('repository') as $class) {
            $container->share($class, function () use ($class) {
                $mapper = $this->get(TarantoolMapper::class);
                $space = $mapper->getPlugin(Annotation::class)->getSpaceName($class);
                return $mapper->getRepository($space);
            });
        }
        $container->share(Spy::class, function (TarantoolMapper $mapper) {
            return $mapper->getPlugin(Spy::class);
        });

        $container->share(TarantoolMapper::class, function (Client $client, Registry $registry) {
            $mapper = new TarantoolMapper($client);
            $mapper->app = $this->app;
            $mapper->service = $this->app->getName();
            $mapper->serviceName = $this->app->getName();

            $annotation = $mapper->getPlugin(Annotation::class);
            array_map([$annotation, 'register'], $registry->listClasses('entity'));
            array_map([$annotation, 'register'], $registry->listClasses('repository'));

            $mapper->getPlugin(Sequence::class);
            $mapper->getPlugin(Spy::class);
            $mapper->getPlugin(Temporal::class)
                ->getAggregator()
                ->setReferenceAggregation(false);

            $mapper->getPlugin(new class ($mapper) extends Plugin {
                public function afterInstantiate(Entity $entity): Entity
                {
                    $entity->app = $this->mapper->app;
                    return $entity;
                }
            });

            return $mapper;
        });
    }
}
