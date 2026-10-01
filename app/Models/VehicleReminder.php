<?php

namespace App\Models;

use App\Enums\ReminderKind;
use App\Exceptions\VehicleReminderImmutable;
use Database\Factories\VehicleReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class VehicleReminder extends Model
{
    /** @use HasFactory<VehicleReminderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (VehicleReminder $reminder): void {
            self::refuse($reminder, 'a reminder records what was sent and is never updated');
        });

        static::deleting(function (VehicleReminder $reminder): void {
            self::refuse($reminder, 'a reminder records what was sent and is never deleted');
        });
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    private static function refuse(VehicleReminder $reminder, string $because): void
    {
        throw new VehicleReminderImmutable($reminder, $because);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ReminderKind::class,
            'due_on' => 'date',
            'sent_at' => 'datetime',
        ];
    }
}
