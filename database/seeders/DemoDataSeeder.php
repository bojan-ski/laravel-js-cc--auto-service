<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Part;
use Database\Seeders\Demo\CatalogueSeeder;
use Database\Seeders\Demo\CustomerSeeder;
use Database\Seeders\Demo\StaffSeeder;
use Database\Seeders\Demo\WorkshopSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be seeded in production.');
        }

        if (Customer::withTrashed()->exists() || Part::withTrashed()->exists()) {
            $this->command?->warn('Demo data skipped: the database already holds customers or parts.');

            return;
        }

        $queue = config('queue.default');
        $mailer = config('mail.default');

        config(['queue.default' => 'sync', 'mail.default' => 'array']);

        try {
            DB::transaction(fn () => $this->call([
                StaffSeeder::class,
                CatalogueSeeder::class,
                CustomerSeeder::class,
                WorkshopSeeder::class,
            ]));
        } finally {
            config(['queue.default' => $queue, 'mail.default' => $mailer]);
        }
    }
}
