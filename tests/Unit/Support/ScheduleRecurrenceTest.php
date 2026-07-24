<?php

namespace Tests\Unit\Support;

use App\Support\ScheduleRecurrence;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ScheduleRecurrenceTest extends TestCase
{
    public function test_daily_matches_every_date(): void
    {
        $this->assertTrue(ScheduleRecurrence::matches('daily', null, null, Carbon::parse('2026-08-05')));
        $this->assertTrue(ScheduleRecurrence::matches('daily', null, null, Carbon::parse('2026-08-06')));
    }

    public function test_weekly_matches_only_listed_weekdays(): void
    {
        // 2026-08-03 is a Monday, 2026-08-04 is a Tuesday.
        $this->assertTrue(ScheduleRecurrence::matches('weekly', [1, 3, 5], null, Carbon::parse('2026-08-03')));
        $this->assertFalse(ScheduleRecurrence::matches('weekly', [1, 3, 5], null, Carbon::parse('2026-08-04')));
    }

    public function test_monthly_matches_only_the_configured_day_of_month(): void
    {
        $this->assertTrue(ScheduleRecurrence::matches('monthly', null, 15, Carbon::parse('2026-08-15')));
        $this->assertFalse(ScheduleRecurrence::matches('monthly', null, 15, Carbon::parse('2026-08-16')));
    }

    public function test_monthly_day_31_produces_no_match_in_shorter_months(): void
    {
        $this->assertFalse(ScheduleRecurrence::matches('monthly', null, 31, Carbon::parse('2026-02-28')));
        $this->assertTrue(ScheduleRecurrence::matches('monthly', null, 31, Carbon::parse('2026-08-31')));
    }

    public function test_monthly_day_29_only_matches_on_leap_year_february(): void
    {
        $this->assertFalse(ScheduleRecurrence::matches('monthly', null, 29, Carbon::parse('2026-02-28'))); // 2026 not a leap year
        $this->assertTrue(ScheduleRecurrence::matches('monthly', null, 29, Carbon::parse('2028-02-29'))); // 2028 is a leap year
    }
}
