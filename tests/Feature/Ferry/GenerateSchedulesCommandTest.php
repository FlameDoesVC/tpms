<?php

namespace Tests\Feature\Ferry;

use App\Models\FerrySchedule;
use App\Models\FerryScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateSchedulesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_command_generates_and_reconciles_ferry_instances(): void
    {
        $template = FerryScheduleTemplate::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2026-08-01',
            'departure_time' => '09:00:00',
        ]);
        $instance = FerrySchedule::factory()->create([
            'ferry_id' => $template->ferry_id,
            'template_id' => $template->id,
            'departure_date' => '2026-08-05',
            'departure_time' => '07:00:00',
        ]);

        $template->update(['departure_time' => '11:00:00']);

        $this->artisan('schedules:generate')->assertExitCode(0);

        $this->assertSame(61, FerrySchedule::where('template_id', $template->id)->count());
        $this->assertSame('11:00:00', $instance->fresh()->departure_time);
    }
}
