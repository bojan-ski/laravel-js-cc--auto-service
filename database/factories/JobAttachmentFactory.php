<?php

namespace Database\Factories;

use App\Enums\AttachmentCategory;
use App\Enums\AttachmentType;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobAttachment>
 */
class JobAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory()->inProgress(),
            'uploaded_by' => User::factory()->mechanic(),
            ...$this->photoAttributes(),
        ];
    }

    public function photo(): static
    {
        return $this->state(fn (): array => $this->photoAttributes());
    }

    public function document(): static
    {
        return $this->state(function (): array {
            $key = (string) Str::ulid();

            return [
                'type' => AttachmentType::Document,
                'path' => "job-attachments/1/{$key}.pdf",
                'variant_full_path' => null,
                'variant_thumb_path' => null,
                'original_filename' => 'inspection-report.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1258291,
            ];
        });
    }

    public function checkIn(): static
    {
        return $this->state(fn (): array => [
            ...$this->photoAttributes(),
            'category' => AttachmentCategory::CheckIn,
            'original_filename' => 'rear-bumper-scratch.jpg',
        ]);
    }

    public function forJob(Job $job): static
    {
        return $this->state(fn (): array => ['job_id' => $job->getKey()]);
    }

    public function uploadedBy(User $user): static
    {
        return $this->state(fn (): array => ['uploaded_by' => $user->getKey()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function photoAttributes(): array
    {
        $key = (string) Str::ulid();

        return [
            'type' => AttachmentType::Photo,
            'path' => "job-attachments/1/{$key}.jpg",
            'variant_full_path' => "job-attachments/1/{$key}_full.webp",
            'variant_thumb_path' => "job-attachments/1/{$key}_thumb.webp",
            'original_filename' => 'front-bumper.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 860160,
        ];
    }
}
