<?php

namespace Basis\Job\Module;

use Basis\Filesystem;
use Basis\Framework;
use Basis\Procedure\Select;
use Basis\Job;
use Exception;
use Tarantool\Mapper\Mapper;
use Tarantool\Mapper\Plugin\Procedure;

class Bootstrap extends Job
{
    public $jobs = ['tarantool.migrate', 'tarantool.cache', 'module.defaults', 'module.register'];

    public function run(Filesystem $fs)
    {
        $result = [];
        $cache = $fs->getPath('.cache');
        if (is_dir($cache)) {
            foreach ($fs->listFiles('.cache') as $file) {
                unlink($fs->getPath('.cache/'.$file));
            }
        }

        try {
            foreach ([Framework::class, Filesystem::class] as $source) {
                $procedures = $this->get($source)->listClasses('Procedure');
                if (count($procedures)) {
                    foreach ($procedures as $procedure) {
                        $this->get(Mapper::class)->getPlugin(Procedure::class)->register($procedure);
                    }
                }
            }
        } catch (Exception $e) {
            // ignore issues on procedure registration
            $result['procedures'] = $e->getMessage();
        }

        foreach ($this->jobs as $job) {
            try {
                $result[$job] = $this->dispatch($job);
            } catch (Exception $e) {
                $result[$job] = $e->getMessage();
            }
        }

        if ($this->app->has(Mapper::class)) {
            $this->get(Mapper::class)->getPlugin(Procedure::class)
                ->register(Select::class);
        }

        return $result;
    }
}
