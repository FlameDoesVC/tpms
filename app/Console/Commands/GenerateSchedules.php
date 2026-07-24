<?php

namespace App\Console\Commands;

use App\Services\ScheduleTemplateGenerator;
use Illuminate\Console\Command;

class GenerateSchedules extends Command
{
    protected $signature = 'schedules:generate';

    protected $description = 'Materialize and reconcile ferry schedules from active recurring templates';

    public function handle(ScheduleTemplateGenerator $generator): int
    {
        $generator->generate();
        $generator->reconcile();

        $this->info('Ferry schedules generated and reconciled.');

        return self::SUCCESS;
    }
}
