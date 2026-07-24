<?php

namespace App\Console\Commands;

use App\Services\EventSlotTemplateGenerator;
use App\Services\ScheduleTemplateGenerator;
use Illuminate\Console\Command;

class GenerateSchedules extends Command
{
    protected $signature = 'schedules:generate';

    protected $description = 'Materialize and reconcile ferry schedules and theme park event slots from active recurring templates';

    public function handle(ScheduleTemplateGenerator $ferryGenerator, EventSlotTemplateGenerator $eventGenerator): int
    {
        $ferryGenerator->generate();
        $ferryGenerator->reconcile();

        $eventGenerator->generate();
        $eventGenerator->reconcile();

        $this->info('Ferry schedules and event slots generated and reconciled.');

        return self::SUCCESS;
    }
}
