<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\HasHistoricalOwnership;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['scheduled_at', 'duration_minutes', 'notes'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    use HasHistoricalOwnership;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AppointmentStatus::Booked->value,
    ];

    /**
     * @return HasOne<Job, $this>
     */
    public function job(): HasOne
    {
        return $this->hasOne(Job::class);
    }

    public function isBooked(): bool
    {
        return $this->status === AppointmentStatus::Booked;
    }

    public function isArrived(): bool
    {
        return $this->status === AppointmentStatus::Arrived;
    }

    public function isMissed(): bool
    {
        return in_array($this->status, [AppointmentStatus::NoShow, AppointmentStatus::Cancelled], true);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function canBeCancelled(): bool
    {
        return $this->isBooked();
    }

    public function canBeMarkedArrived(): bool
    {
        return $this->isBooked();
    }

    public function canBeMarkedNoShow(): bool
    {
        return $this->isBooked();
    }

    public function canBeRescheduled(): bool
    {
        return $this->isBooked();
    }

    public function endsAt(): CarbonInterface
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes);
    }

    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $start, int $durationMinutes, ?int $excludeId = null): void
    {
        $query->where('status', AppointmentStatus::Booked)
            ->where('scheduled_at', '<', $start->copy()->addMinutes($durationMinutes))
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$start])
            ->when($excludeId !== null, fn (Builder $query) => $query->whereKeyNot($excludeId));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'scheduled_at' => 'datetime',
            'arrived_at' => 'datetime',
            'no_show_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }
}
