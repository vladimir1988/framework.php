<?php

namespace Basis;

use League\Container\Container;
use League\Container\ReflectionContainer;
use Tarantool\Mapper\Mapper;
use Tarantool\Mapper\Plugins\Annotation;
use Tarantool\Mapper\Repository;

class Application extends Container
{
    public function __construct($root)
    {
        parent::__construct();

        $fs = new Filesystem($this, $root);

        $this->share(Application::class, $this);
        $this->share(Container::class, $this);
        $this->share(Filesystem::class, $fs);

        $this->addServiceProvider(Providers\CoreProvider::class);
        $this->addServiceProvider(Providers\ServiceProvider::class);
        $this->addServiceProvider(Providers\TarantoolProvider::class);

        foreach ($fs->listClasses('Providers') as $provider) {
            $this->addServiceProvider($provider);
        }

        $this->delegate(new ReflectionContainer());
    }

    public function dispatch($command, $params = [])
    {
        return $this->get(Runner::class)->dispatch($command, $params);
    }

    public function get($alias, array $args = [])
    {
        if (!$this->hasShared($alias, true) && is_subclass_of($alias, Repository::class)) {
            $spaceName = $this->get(Mapper::class)
                ->getPlugin(Annotation::class)
                ->getSpaceName($alias);

            if ($spaceName) {
                $instance = $this->get(Mapper::class)->getRepository($spaceName);
                $this->share($alias, $instance);
            }
        }
        return parent::get($alias, $args);
    }
}
