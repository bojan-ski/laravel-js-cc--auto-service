<?php

namespace Database\Factories;

use App\Enums\FuelLevel;
use App\Enums\SignatureMethod;
use App\Models\Job;
use App\Models\JobCheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobCheckIn>
 */
class JobCheckInFactory extends Factory
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
            'fuel_level' => fake()->randomElement(FuelLevel::cases()),
            'damage_notes' => null,
            'valuables' => null,
            'recorded_by' => User::factory()->mechanic(),
        ];
    }

    public function forJob(Job $job): static
    {
        return $this->state(fn (): array => ['job_id' => $job->getKey()]);
    }

    public function recordedBy(User $user): static
    {
        return $this->state(fn (): array => ['recorded_by' => $user->getKey()]);
    }

    public function signedOnPaper(): static
    {
        return $this->state(fn (): array => [
            'signed_by_name' => fake()->name(),
            'signature_method' => SignatureMethod::Paper,
            'signature_path' => null,
            'signed_at' => now(),
        ]);
    }

    public function signedByDrawing(): static
    {
        return $this->state(fn (): array => [
            'signed_by_name' => fake()->name(),
            'signature_method' => SignatureMethod::Drawn,
            'signature_path' => fn (array $attributes): string => $this->storeSignature((int) $attributes['job_id']),
            'signed_at' => now(),
        ]);
    }

    private function storeSignature(int $jobId): string
    {
        $path = config('attachments.directory')."/{$jobId}/signature_".Str::ulid().'.png';

        $image = imagecreatetruecolor(600, 200);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagesetthickness($image, 3);
        imageline($image, 40, 150, 560, 60, (int) imagecolorallocate($image, 0, 0, 0));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        Storage::disk(config('attachments.disk'))->put($path, $bytes);

        return $path;
    }
}
