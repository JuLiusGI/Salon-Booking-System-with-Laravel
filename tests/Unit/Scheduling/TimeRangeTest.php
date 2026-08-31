<?php

namespace Tests\Unit\Scheduling;

use App\Services\Scheduling\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TimeRangeTest extends TestCase
{
    private function range(string $start, string $end): TimeRange
    {
        return new TimeRange(
            CarbonImmutable::parse("2026-09-15 {$start}", 'UTC'),
            CarbonImmutable::parse("2026-09-15 {$end}", 'UTC'),
        );
    }

    public function test_a_range_must_end_after_it_starts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->range('10:00', '10:00');
    }

    public function test_a_backwards_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->range('11:00', '10:00');
    }

    public function test_overlapping_is_half_open_so_back_to_back_ranges_do_not_collide(): void
    {
        $first = $this->range('10:00', '11:00');
        $second = $this->range('11:00', '12:00');

        $this->assertFalse($first->overlaps($second));
        $this->assertFalse($second->overlaps($first));
    }

    public function test_a_one_minute_overlap_still_counts(): void
    {
        $this->assertTrue($this->range('10:00', '11:00')->overlaps($this->range('10:59', '12:00')));
    }

    public function test_subtracting_a_block_in_the_middle_splits_the_range(): void
    {
        $pieces = $this->range('09:00', '17:00')->subtract($this->range('12:00', '13:00'));

        $this->assertCount(2, $pieces);
        $this->assertTrue($pieces[0]->equalTo($this->range('09:00', '12:00')));
        $this->assertTrue($pieces[1]->equalTo($this->range('13:00', '17:00')));
    }

    public function test_subtracting_a_block_at_the_start_trims_the_front(): void
    {
        $pieces = $this->range('09:00', '17:00')->subtract($this->range('08:00', '10:00'));

        $this->assertCount(1, $pieces);
        $this->assertTrue($pieces[0]->equalTo($this->range('10:00', '17:00')));
    }

    public function test_subtracting_a_block_at_the_end_trims_the_back(): void
    {
        $pieces = $this->range('09:00', '17:00')->subtract($this->range('16:00', '19:00'));

        $this->assertCount(1, $pieces);
        $this->assertTrue($pieces[0]->equalTo($this->range('09:00', '16:00')));
    }

    public function test_a_block_covering_everything_leaves_nothing(): void
    {
        $this->assertSame([], $this->range('09:00', '17:00')->subtract($this->range('08:00', '18:00')));
    }

    public function test_a_block_that_does_not_touch_leaves_the_range_untouched(): void
    {
        $pieces = $this->range('09:00', '12:00')->subtract($this->range('13:00', '14:00'));

        $this->assertCount(1, $pieces);
        $this->assertTrue($pieces[0]->equalTo($this->range('09:00', '12:00')));
    }

    public function test_a_block_ending_exactly_when_the_range_starts_removes_nothing(): void
    {
        $pieces = $this->range('09:00', '17:00')->subtract($this->range('08:00', '09:00'));

        $this->assertCount(1, $pieces);
        $this->assertTrue($pieces[0]->equalTo($this->range('09:00', '17:00')));
    }

    public function test_subtracting_several_blocks_leaves_the_gaps_between_them(): void
    {
        $result = TimeRange::subtractAll(
            [$this->range('09:00', '17:00')],
            [$this->range('11:00', '11:30'), $this->range('13:00', '14:00')],
        );

        $this->assertCount(3, $result);
        $this->assertTrue($result[0]->equalTo($this->range('09:00', '11:00')));
        $this->assertTrue($result[1]->equalTo($this->range('11:30', '13:00')));
        $this->assertTrue($result[2]->equalTo($this->range('14:00', '17:00')));
    }

    public function test_intersecting_keeps_only_the_shared_part(): void
    {
        $shared = $this->range('09:00', '17:00')->intersect($this->range('12:00', '20:00'));

        $this->assertNotNull($shared);
        $this->assertTrue($shared->equalTo($this->range('12:00', '17:00')));
    }

    public function test_intersecting_ranges_that_do_not_touch_gives_nothing(): void
    {
        $this->assertNull($this->range('09:00', '12:00')->intersect($this->range('12:00', '15:00')));
    }

    public function test_intersect_all_pairs_every_combination(): void
    {
        $result = TimeRange::intersectAll(
            [$this->range('09:00', '18:00')],
            [$this->range('08:00', '12:00'), $this->range('14:00', '20:00')],
        );

        $this->assertCount(2, $result);
        $this->assertTrue($result[0]->equalTo($this->range('09:00', '12:00')));
        $this->assertTrue($result[1]->equalTo($this->range('14:00', '18:00')));
    }

    public function test_expanding_grows_the_range_on_both_sides(): void
    {
        $expanded = $this->range('10:00', '11:00')->expandedBy(15);

        $this->assertTrue($expanded->equalTo($this->range('09:45', '11:15')));
    }

    public function test_expanding_by_zero_changes_nothing(): void
    {
        $original = $this->range('10:00', '11:00');

        $this->assertTrue($original->expandedBy(0)->equalTo($original));
    }

    public function test_duration_is_reported_in_minutes(): void
    {
        $this->assertSame(90, $this->range('10:00', '11:30')->durationMinutes());
    }

    public function test_ranges_are_normalised_to_utc_however_they_are_built(): void
    {
        $manila = new TimeRange(
            CarbonImmutable::parse('2026-09-15 09:00', 'Asia/Manila'),
            CarbonImmutable::parse('2026-09-15 17:00', 'Asia/Manila'),
        );

        // 09:00 in Manila is 01:00 UTC.
        $this->assertSame('01:00', $manila->start->format('H:i'));
        $this->assertSame('UTC', $manila->start->tzName);
        $this->assertSame(480, $manila->durationMinutes());
    }

    public function test_ranges_built_in_different_zones_compare_correctly(): void
    {
        $manila = new TimeRange(
            CarbonImmutable::parse('2026-09-15 09:00', 'Asia/Manila'),
            CarbonImmutable::parse('2026-09-15 10:00', 'Asia/Manila'),
        );

        $utc = $this->range('01:00', '02:00');

        // The same hour expressed two ways must be recognised as identical,
        // never compared as ambiguous strings (MASTER_SPEC section 10).
        $this->assertTrue($manila->equalTo($utc));
        $this->assertTrue($manila->overlaps($utc));
    }
}
