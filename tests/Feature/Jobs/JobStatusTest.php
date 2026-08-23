<?php

namespace Tests\Feature\Jobs;

use App\Contracts\ProvidesStatusPresentation;
use App\Enums\JobStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobStatusTest extends TestCase
{
    public function test_the_enum_has_exactly_the_five_lifecycle_cases(): void
    {
        $this->assertSame(
            ['received', 'in_progress', 'awaiting_parts', 'completed', 'cancelled'],
            array_map(fn (JobStatus $status): string => $status->value, JobStatus::cases()),
        );
    }

    public function test_the_enum_provides_status_presentation(): void
    {
        $this->assertInstanceOf(ProvidesStatusPresentation::class, JobStatus::Received);
    }

    /**
     * @return array<string, array{0: JobStatus, 1: string, 2: string, 3: bool}>
     */
    public static function statusProvider(): array
    {
        return [
            'received' => [JobStatus::Received, 'Received', 'status-received', true],
            'in progress' => [JobStatus::InProgress, 'In progress', 'status-in-progress', true],
            'awaiting parts' => [JobStatus::AwaitingParts, 'Awaiting parts', 'status-awaiting-parts', true],
            'completed' => [JobStatus::Completed, 'Completed', 'status-completed', false],
            'cancelled' => [JobStatus::Cancelled, 'Cancelled', 'status-cancelled', false],
        ];
    }

    #[DataProvider('statusProvider')]
    public function test_each_case_presents_its_label_token_and_working_state(JobStatus $status, string $label, string $token, bool $working): void
    {
        $this->assertSame($label, $status->label());
        $this->assertSame($token, $status->colorToken());
        $this->assertSame($working, $status->isWorking());
        $this->assertSame(! $working, $status->isTerminal());
    }

    #[DataProvider('statusProvider')]
    public function test_the_status_badge_renders_literal_classes_for_each_job_status(JobStatus $status, string $label, string $token): void
    {
        $rendered = $this->blade('<x-ui.status-badge :status="$status" />', ['status' => $status]);

        $rendered->assertSee("bg-{$token}/10", false);
        $rendered->assertSee("text-{$token}", false);
        $rendered->assertSee($label);
    }
}
