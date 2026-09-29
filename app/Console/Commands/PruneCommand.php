<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Database\Console\PruneCommand as BasePruneCommand;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'model:prune')]
class PruneCommand extends BasePruneCommand
{
    /**
     * Get the path where models are located.
     *
     * @return string[]|string
     */
    protected function getPath()
    {
        if (! empty($path = $this->option('path'))) {
            return (new Collection($path))
                ->map(fn ($path) => base_path($path))
                ->all();
        }

        $defaultPaths = [
            app_path('Domain'),
            app_path('Infrastructure'),
        ];

        if (is_dir(app_path('Models'))) {
            $defaultPaths[] = app_path('Models');
        }

        return array_values(array_filter($defaultPaths, 'is_dir'));
    }
}
