<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

abstract class DemoSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    public const string EMAIL_DOMAIN = 'auto-service.test';

    protected CarbonImmutable $today;

    /** @var array<string, User> */
    private array $staff = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be seeded in production.');
        }

        $this->today = CarbonImmutable::now();

        try {
            $this->populate();
        } finally {
            Carbon::setTestNow();
        }
    }

    abstract protected function populate(): void;

    protected function daysAgo(int $days, string $time = '09:00'): CarbonImmutable
    {
        return $this->today->subDays($days)->setTimeFromTimeString($time);
    }

    protected function travelTo(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment->min($this->today));
    }

    protected function staff(string $handle): User
    {
        return $this->staff[$handle] ??= User::withTrashed()
            ->where('email', self::emailFor($handle))
            ->firstOrFail();
    }

    public static function emailFor(string $handle): string
    {
        return $handle.'@'.self::EMAIL_DOMAIN;
    }
}
