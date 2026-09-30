<?php

namespace Database\Seeders\Demo;

use App\Enums\JobItemType;
use App\Models\JobTemplate;
use App\Models\Part;
use App\Services\JobTemplateService;

class JobTemplateSeeder extends DemoSeeder
{
    public const string PETROL_SERVICE = 'Small service (petrol)';

    public const string DIESEL_SERVICE = 'Small service (diesel)';

    public const string FRONT_BRAKE_PADS = 'Front brake pads';

    public const string BUDGET_SERVICE = 'Small service (budget)';

    /**
     * name, description, lines: [type, labor description or SKU, quantity]
     *
     * @var list<array{string, string, list<array{JobItemType, string, int|float}>}>
     */
    private const array TEMPLATES = [
        [self::PETROL_SERVICE, 'Oil, oil filter and air filter for a petrol engine.', [
            [JobItemType::Labor, 'Oil and filter service', 1],
            [JobItemType::Part, 'OF-1001', 1],
            [JobItemType::Part, 'EO-6001', 5],
            [JobItemType::Part, 'AF-1101', 1],
        ]],
        [self::DIESEL_SERVICE, 'Oil, oil filter and air filter for a diesel engine.', [
            [JobItemType::Labor, 'Oil and filter service', 1],
            [JobItemType::Part, 'OF-1003', 1],
            [JobItemType::Part, 'EO-6002', 5],
            [JobItemType::Part, 'AF-1102', 1],
        ]],
        [self::FRONT_BRAKE_PADS, 'Pads only; discs quoted separately.', [
            [JobItemType::Labor, 'Front brake pads replacement', 1],
            [JobItemType::Part, 'BP-2002', 1],
        ]],
        [self::BUDGET_SERVICE, 'Cheapest oil service, on the Stark filter.', [
            [JobItemType::Labor, 'Oil and filter service', 1],
            [JobItemType::Part, 'OF-0999', 1],
            [JobItemType::Part, 'EO-6002', 4],
        ]],
    ];

    public function __construct(
        private readonly JobTemplateService $templates,
    ) {}

    protected function populate(): void
    {
        foreach (self::TEMPLATES as [$name, $description, $lines]) {
            $template = $this->templates->create(['name' => $name, 'description' => $description]);

            foreach ($lines as [$type, $subject, $quantity]) {
                $this->addLine($template, $type, $subject, $quantity);
            }
        }
    }

    private function addLine(JobTemplate $template, JobItemType $type, string $subject, int|float $quantity): void
    {
        $this->templates->addItem($template, $type, $type === JobItemType::Labor
            ? ['description' => $subject, 'quantity' => $quantity]
            : ['part_id' => Part::query()->where('sku', $subject)->value('id'), 'quantity' => $quantity]);
    }
}
