<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['appointments_open_at', 'appointments_close_at'])]
class ShopSetting extends Model
{
    public function opensAt(): string
    {
        return substr((string) $this->appointments_open_at, 0, 5);
    }

    public function closesAt(): string
    {
        return substr((string) $this->appointments_close_at, 0, 5);
    }

    public function allowsAppointment(CarbonInterface $start, int $durationMinutes): bool
    {
        $start = CarbonImmutable::instance($start)->startOfMinute();

        return $start->greaterThanOrEqualTo($start->setTimeFromTimeString($this->opensAt()))
            && $start->addMinutes($durationMinutes)->lessThanOrEqualTo($start->setTimeFromTimeString($this->closesAt()));
    }

    public function earliestAppointmentStart(): CarbonImmutable
    {
        return CarbonImmutable::tomorrow()->setTimeFromTimeString($this->opensAt());
    }
}
