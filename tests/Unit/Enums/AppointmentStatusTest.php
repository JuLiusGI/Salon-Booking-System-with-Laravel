<?php

namespace Tests\Unit\Enums;

use App\Enums\AppointmentStatus;
use PHPUnit\Framework\TestCase;

class AppointmentStatusTest extends TestCase
{
    public function test_pending_may_only_move_to_confirmed_cancelled_or_no_show(): void
    {
        $status = AppointmentStatus::Pending;

        $this->assertTrue($status->canTransitionTo(AppointmentStatus::Confirmed));
        $this->assertTrue($status->canTransitionTo(AppointmentStatus::Cancelled));
        $this->assertTrue($status->canTransitionTo(AppointmentStatus::NoShow));

        $this->assertFalse($status->canTransitionTo(AppointmentStatus::Completed));
        $this->assertFalse($status->canTransitionTo(AppointmentStatus::InProgress));
        $this->assertFalse($status->canTransitionTo(AppointmentStatus::CheckedIn));
    }

    public function test_an_appointment_cannot_be_completed_without_being_in_progress(): void
    {
        $this->assertFalse(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Completed));
        $this->assertFalse(AppointmentStatus::CheckedIn->canTransitionTo(AppointmentStatus::Completed));
        $this->assertTrue(AppointmentStatus::InProgress->canTransitionTo(AppointmentStatus::Completed));
    }

    public function test_terminal_statuses_allow_no_further_transitions(): void
    {
        foreach ([AppointmentStatus::Completed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow] as $terminal) {
            $this->assertTrue($terminal->isTerminal(), "{$terminal->value} should be terminal");
            $this->assertSame([], $terminal->allowedTransitions());

            foreach (AppointmentStatus::cases() as $target) {
                $this->assertFalse(
                    $terminal->canTransitionTo($target),
                    "{$terminal->value} must not transition to {$target->value}"
                );
            }
        }
    }

    public function test_no_status_can_transition_to_itself(): void
    {
        foreach (AppointmentStatus::cases() as $status) {
            $this->assertFalse($status->canTransitionTo($status));
        }
    }

    public function test_a_completed_appointment_cannot_be_reopened(): void
    {
        $this->assertFalse(AppointmentStatus::Completed->canTransitionTo(AppointmentStatus::InProgress));
        $this->assertFalse(AppointmentStatus::Completed->canTransitionTo(AppointmentStatus::Cancelled));
    }

    public function test_only_cancelled_and_no_show_free_up_the_schedule(): void
    {
        $this->assertFalse(AppointmentStatus::Cancelled->blocksAvailability());
        $this->assertFalse(AppointmentStatus::NoShow->blocksAvailability());

        foreach ([
            AppointmentStatus::Pending,
            AppointmentStatus::Confirmed,
            AppointmentStatus::CheckedIn,
            AppointmentStatus::InProgress,
            AppointmentStatus::Completed,
        ] as $status) {
            $this->assertTrue($status->blocksAvailability(), "{$status->value} should block availability");
        }
    }

    public function test_every_case_exposes_a_label_and_value(): void
    {
        foreach (AppointmentStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }

        $this->assertCount(7, AppointmentStatus::values());
    }
}
