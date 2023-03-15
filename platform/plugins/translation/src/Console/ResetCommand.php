<?php

namespace Botble\Translation\Console;

use Illuminate\Console\Command;
use Botble\Translation\Manager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('cms:translations:reset', 'Delete all languages records in database')]
class ResetCommand extends Command
{
    public function handle(Manager $manager): int
    {
        $manager->truncateTranslations();
        $this->info('All translations are deleted');

        return self::SUCCESS;
    }
}
