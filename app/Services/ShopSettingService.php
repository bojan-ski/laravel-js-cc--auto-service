<?php

namespace App\Services;

use App\Models\ShopSetting;

class ShopSettingService
{
    public function current(): ShopSetting
    {
        return ShopSetting::query()->orderBy('id')->first() ?? new ShopSetting([
            'appointments_open_at' => config('shop.appointment_hours.open'),
            'appointments_close_at' => config('shop.appointment_hours.close'),
        ]);
    }

    public function updateAppointmentHours(string $openAt, string $closeAt): ShopSetting
    {
        $settings = $this->current();

        $settings->fill([
            'appointments_open_at' => $openAt,
            'appointments_close_at' => $closeAt,
        ]);

        $settings->save();

        return $settings;
    }
}
