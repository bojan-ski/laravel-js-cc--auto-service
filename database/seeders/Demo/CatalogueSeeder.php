<?php

namespace Database\Seeders\Demo;

use App\Enums\PartNumberType;
use App\Enums\QualityTier;
use App\Enums\StockMovementReason;
use App\Models\Part;
use App\Services\BrandService;
use App\Services\PartNumberService;
use App\Services\PartService;
use App\Services\StockService;

class CatalogueSeeder extends DemoSeeder
{
    /** @var array<string, QualityTier> */
    private const array BRANDS = [
        'Bosch' => QualityTier::FirstFit,
        'Mann-Filter' => QualityTier::FirstFit,
        'Brembo' => QualityTier::FirstFit,
        'ATE' => QualityTier::FirstFit,
        'NGK' => QualityTier::FirstFit,
        'Valeo' => QualityTier::Original,
        'Febi Bilstein' => QualityTier::Original,
        'Castrol' => QualityTier::Original,
        'VAG Genuine' => QualityTier::Genuine,
        'Ford Genuine' => QualityTier::Genuine,
        'Ridex' => QualityTier::Aftermarket,
        'Stark' => QualityTier::Aftermarket,
        'Maxgear' => QualityTier::Aftermarket,
    ];

    private const string RETIRED_BRAND = 'Maxgear';

    private const string RETIRED_PART = 'OF-0999';

    /**
     * sku, name, brand, category, unit price, cost price, reorder level, location, opening stock, numbers, description
     *
     * @var list<array{string, string, ?string, string, int, int, int, string, int, list<array{PartNumberType, string}>, ?string}>
     */
    public const array PARTS = [
        ['OF-1001', 'Oil filter', 'Mann-Filter', 'Filters', 1450, 820, 5, 'A1', 18, [[PartNumberType::Catalog, 'W 712/95'], [PartNumberType::Oe, '03C 115 561 H'], [PartNumberType::Ean, '4011558738804']], null],
        ['OF-1002', 'Oil filter', 'Bosch', 'Filters', 1290, 700, 5, 'A1', 14, [[PartNumberType::Catalog, 'F 026 407 183'], [PartNumberType::Oe, '03C 115 561 H']], null],
        ['OF-1003', 'Oil filter', 'Ridex', 'Filters', 690, 380, 3, 'A1', 10, [[PartNumberType::Catalog, '7O0012'], [PartNumberType::Oe, '03C 115 561 H']], 'Budget option, same fitment as OF-1001.'],
        ['OF-1010', 'Oil filter', 'Ford Genuine', 'Filters', 1850, 1100, 2, 'A2', 6, [[PartNumberType::Oe, '1 883 037']], null],
        ['AF-1101', 'Air filter', 'Mann-Filter', 'Filters', 2350, 1300, 3, 'A3', 8, [[PartNumberType::Catalog, 'C 27 107'], [PartNumberType::Oe, '1K0 129 620 D']], null],
        ['AF-1102', 'Air filter', 'Bosch', 'Filters', 2100, 1150, 3, 'A3', 5, [[PartNumberType::Catalog, 'F 026 400 052']], null],
        ['CF-1201', 'Cabin filter', 'Mann-Filter', 'Filters', 1900, 1050, 3, 'A4', 7, [[PartNumberType::Catalog, 'CU 2939'], [PartNumberType::Oe, '1K1 819 653 B']], null],
        ['CF-1202', 'Cabin filter, activated carbon', 'Bosch', 'Filters', 2600, 1500, 2, 'A4', 2, [[PartNumberType::Catalog, '1 987 432 397']], null],
        ['FF-1301', 'Fuel filter, diesel', 'Mann-Filter', 'Filters', 3200, 1900, 2, 'A5', 4, [[PartNumberType::Catalog, 'WK 853/3 x'], [PartNumberType::Oe, '1K0 127 434 A']], null],
        ['BP-2001', 'Brake pads front', 'ATE', 'Brakes', 5400, 3200, 2, 'B1', 6, [[PartNumberType::Catalog, '13.0460-7218.2'], [PartNumberType::Oe, '1K0 698 151 B'], [PartNumberType::Ean, '4006633345236']], null],
        ['BP-2002', 'Brake pads front', 'Ridex', 'Brakes', 2900, 1500, 2, 'B1', 4, [[PartNumberType::Catalog, '402B0024'], [PartNumberType::Oe, '1K0 698 151 B']], null],
        ['BP-2003', 'Brake pads rear', 'Brembo', 'Brakes', 4800, 2800, 2, 'B2', 5, [[PartNumberType::Catalog, 'P 85 073'], [PartNumberType::Oe, '1K0 698 451 G']], null],
        ['BD-2101', 'Brake disc front, 288 mm', 'Brembo', 'Brakes', 6900, 4100, 2, 'B3', 6, [[PartNumberType::Catalog, '09.9772.11'], [PartNumberType::Oe, '1K0 615 301 AA']], 'Sold individually; fit in pairs.'],
        ['BD-2102', 'Brake disc rear, 253 mm', 'ATE', 'Brakes', 5200, 3000, 2, 'B3', 4, [[PartNumberType::Catalog, '24.0110-0277.1'], [PartNumberType::Oe, '1K0 615 601 AB']], null],
        ['BF-2201', 'Brake fluid DOT 4, 1 l', 'ATE', 'Brakes', 1350, 700, 4, 'F1', 12, [[PartNumberType::Catalog, '03.9901-6402.2']], null],
        ['BS-2301', 'Brake pad wear sensor', 'Febi Bilstein', 'Brakes', 950, 450, 2, 'B4', 3, [[PartNumberType::Catalog, '24486']], null],
        ['SP-3001', 'Spark plug', 'NGK', 'Ignition', 690, 350, 8, 'C1', 24, [[PartNumberType::Catalog, 'BKR6E'], [PartNumberType::Ean, '0087295119018']], null],
        ['SP-3002', 'Spark plug, iridium', 'Bosch', 'Ignition', 1850, 1100, 4, 'C1', 8, [[PartNumberType::Catalog, 'FR7KI332S']], null],
        ['GP-3101', 'Glow plug', 'Bosch', 'Ignition', 2400, 1400, 4, 'C2', 12, [[PartNumberType::Catalog, '0 250 202 132'], [PartNumberType::Oe, 'N 105 916 03']], null],
        ['IC-3201', 'Ignition coil', 'Valeo', 'Ignition', 7400, 4600, 1, 'C3', 2, [[PartNumberType::Catalog, '245103'], [PartNumberType::Oe, '036 905 715 F']], null],
        ['TB-3301', 'Timing belt kit', 'Febi Bilstein', 'Engine', 18500, 12000, 1, 'D1', 1, [[PartNumberType::Catalog, '45115'], [PartNumberType::Supplier, 'DLT-45115']], 'Belt, tensioner and idler.'],
        ['WP-3401', 'Water pump', 'Febi Bilstein', 'Engine', 9800, 6200, 1, 'D2', 0, [[PartNumberType::Catalog, '26405']], null],
        ['SB-3501', 'Auxiliary drive belt 6PK1070', 'Bosch', 'Engine', 2200, 1200, 2, 'D3', 5, [[PartNumberType::Catalog, '1 987 947 823']], null],
        ['TS-3601', 'Thermostat', 'Febi Bilstein', 'Engine', 3400, 2000, 1, 'D4', 2, [[PartNumberType::Catalog, '38743']], null],
        ['CK-8001', 'Clutch kit, 3-piece', 'Valeo', 'Clutch', 24900, 17500, 1, 'D5', 1, [[PartNumberType::Catalog, '826317']], null],
        ['SA-4001', 'Shock absorber front', 'Stark', 'Suspension', 5200, 3000, 2, 'E1', 6, [[PartNumberType::Catalog, 'SKSA-0130361']], null],
        ['CL-4101', 'Control arm, front lower', 'Febi Bilstein', 'Suspension', 6200, 3700, 1, 'E2', 3, [[PartNumberType::Catalog, '27069'], [PartNumberType::Oe, '1K0 407 151 AC']], null],
        ['TR-4201', 'Tie rod end', 'Febi Bilstein', 'Suspension', 2300, 1300, 2, 'E3', 6, [[PartNumberType::Catalog, '26095']], null],
        ['SL-4301', 'Stabilizer link', 'Ridex', 'Suspension', 1600, 800, 2, 'E4', 6, [[PartNumberType::Catalog, '3229S0012']], null],
        ['WB-4401', 'Wheel bearing kit, front', 'Febi Bilstein', 'Suspension', 5800, 3500, 1, 'E5', 2, [[PartNumberType::Catalog, '19193']], null],
        ['BA-5001', 'Battery 12 V 60 Ah', 'Bosch', 'Electrical', 13900, 9800, 2, 'G1', 4, [[PartNumberType::Catalog, 'S4 005'], [PartNumberType::Ean, '4047024637419']], null],
        ['BA-5002', 'Battery 12 V 74 Ah', 'Bosch', 'Electrical', 16500, 11800, 1, 'G1', 2, [[PartNumberType::Catalog, 'S4 008']], null],
        ['BL-5101', 'Bulb H7 12 V 55 W', 'Bosch', 'Electrical', 450, 180, 10, 'G2', 30, [[PartNumberType::Catalog, '1 987 302 071']], null],
        ['BL-5102', 'Bulb W5W', null, 'Electrical', 90, 30, 20, 'G2', 50, [], null],
        ['EO-6001', 'Engine oil 5W-30 LL, 1 l', 'Castrol', 'Fluids', 1450, 900, 20, 'F2', 60, [[PartNumberType::Catalog, 'EDGE 5W-30 LL'], [PartNumberType::Ean, '4008177071829']], 'Meets VW 504 00 / 507 00.'],
        ['EO-6002', 'Engine oil 5W-40, 1 l', 'Castrol', 'Fluids', 1250, 780, 12, 'F2', 40, [[PartNumberType::Catalog, 'MAGNATEC 5W-40']], null],
        ['AC-6101', 'Coolant G12++, 1 l', 'VAG Genuine', 'Fluids', 950, 520, 6, 'F3', 20, [[PartNumberType::Oe, 'G 013 A8J M1']], null],
        ['WW-6201', 'Screen wash, winter, 5 l', null, 'Fluids', 850, 400, 6, 'F4', 12, [], null],
        ['WB-7001', 'Wiper blade 24"', 'Bosch', 'Wipers', 2400, 1300, 4, 'H1', 4, [[PartNumberType::Catalog, '3 397 118 979']], null],
        ['WB-7002', 'Wiper blade 20"', 'Valeo', 'Wipers', 2100, 1150, 4, 'H1', 8, [[PartNumberType::Catalog, '574285']], null],
        [self::RETIRED_PART, 'Oil filter (discontinued)', 'Stark', 'Filters', 590, 300, 0, 'A9', 3, [[PartNumberType::Catalog, 'SKOF-0860001']], 'Replaced by OF-1003.'],
    ];

    public function __construct(
        private readonly BrandService $brands,
        private readonly PartService $parts,
        private readonly PartNumberService $partNumbers,
        private readonly StockService $stock,
    ) {}

    protected function populate(): void
    {
        $this->travelTo($this->daysAgo(120, '10:00'));

        $brands = [];

        foreach (self::BRANDS as $name => $tier) {
            $brands[$name] = $this->brands->create(['name' => $name, 'quality_tier' => $tier]);
        }

        $this->travelTo($this->daysAgo(95, '10:00'));

        $opening = [];

        foreach (self::PARTS as [$sku, $name, $brand, $category, $unitPrice, $costPrice, $reorderLevel, $location, $openingStock, $numbers, $description]) {
            $part = $this->parts->create([
                'sku' => $sku,
                'name' => $name,
                'brand_id' => $brand === null ? null : $brands[$brand]->getKey(),
                'category' => $category,
                'description' => $description,
                'unit_price' => $unitPrice,
                'cost_price' => $costPrice,
                'reorder_level' => $reorderLevel,
                'location' => $location,
            ]);

            foreach ($numbers as [$type, $number]) {
                $this->partNumbers->add($part, $type, $number);
            }

            if ($openingStock > 0) {
                $opening[] = [$part, $openingStock];
            }
        }

        $this->travelTo($this->daysAgo(90, '16:00'));

        foreach ($opening as [$part, $quantity]) {
            $this->stock->record($part, $quantity, StockMovementReason::ManualAdjustment, $this->staff('jelena'), note: 'Opening stock count');
        }

        $this->travelTo($this->daysAgo(75, '15:00'));
        $this->call(JobTemplateSeeder::class);

        $this->travelTo($this->daysAgo(60, '12:00'));
        $this->brands->delete($brands[self::RETIRED_BRAND]);

        $this->travelTo($this->daysAgo(40, '12:00'));
        $this->parts->delete(Part::query()->where('sku', self::RETIRED_PART)->firstOrFail());
    }
}
