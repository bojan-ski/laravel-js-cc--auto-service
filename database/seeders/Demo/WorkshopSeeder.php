<?php

namespace Database\Seeders\Demo;

use App\Enums\JobItemType;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementReason;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\JobTemplate;
use App\Models\Part;
use App\Models\Vehicle;
use App\Services\AppointmentService;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\JobItemService;
use App\Services\JobService;
use App\Services\StaffService;
use App\Services\StockService;
use App\Services\SupplierService;
use App\Services\VehicleService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;

class WorkshopSeeder extends DemoSeeder
{
    private const string VOIDING_ADMIN = 'milica';

    private const int DUE_DAYS = 15;

    private const int FLEET_DUE_DAYS = 30;

    private const string FLEET_LABOR_DISCOUNT = '10.00';

    /**
     * Outcomes: received, in_progress, awaiting_parts, cancelled, completed, unpaid, paid, voided, reissued.
     * Lines: [type, mechanic (null = added unassigned by the desk), description or SKU, quantity].
     * Discounts: [line index => percent], set after completion and before the invoice.
     *
     * @var list<array<string, mixed>>
     */
    public const array JOBS = [
        ['plate' => 'BG-123-AB', 'day' => 58, 'at' => '08:10', 'booked' => '08:00', 'by' => 'jelena', 'mileage' => 184320, 'complaint' => 'Pulls right under braking; squeal from the front.', 'outcome' => 'paid', 'lines' => [
            [JobItemType::Labor, 'dragan', 'Brake service front: pads and discs', 1.5],
            [JobItemType::Part, 'dragan', 'BP-2001', 1],
            [JobItemType::Part, 'dragan', 'BD-2101', 2],
            [JobItemType::Part, 'dragan', 'BF-2201', 1],
            [JobItemType::Labor, 'dragan', 'Brake fluid flush', 0.5],
        ]],
        ['plate' => 'NS-221-TT', 'day' => 57, 'at' => '09:00', 'booked' => null, 'by' => 'ana', 'mileage' => 213050, 'complaint' => 'Regular service: oil and filters.', 'outcome' => 'paid', 'paid_after' => 3, 'lines' => [], 'template' => [JobTemplateSeeder::DIESEL_SERVICE, 'ana', 'zoran']],
        ['plate' => 'BG-2101-BK', 'day' => 56, 'at' => '08:30', 'booked' => null, 'by' => 'jelena', 'mileage' => 242150, 'complaint' => 'Clutch slipping under load.', 'outcome' => 'paid', 'paid_after' => 14, 'due' => self::FLEET_DUE_DAYS, 'discounts' => [0 => self::FLEET_LABOR_DISCOUNT], 'lines' => [
            [JobItemType::Labor, 'dragan', 'Clutch replacement', 4.5],
            [JobItemType::Part, 'dragan', 'CK-8001', 1],
        ]],
        ['plate' => 'BG-315-DM', 'day' => 55, 'at' => '10:05', 'booked' => '10:00', 'by' => 'ana', 'mileage' => 257480, 'complaint' => 'Engine warning light; rough idle when cold.', 'outcome' => 'paid', 'lines' => [
            [JobItemType::Labor, 'nikola', 'Diagnostics', 0.5],
            [JobItemType::Labor, 'nikola', 'Glow plug replacement', 1.5],
            [JobItemType::Part, 'nikola', 'GP-3101', 4],
        ]],
        ['plate' => 'NI-552-NS', 'day' => 53, 'at' => '08:15', 'booked' => null, 'by' => 'jelena', 'mileage' => 268900, 'complaint' => 'Knocking from the front suspension over bumps.', 'outcome' => 'paid', 'paid_after' => 1, 'lines' => [
            [JobItemType::Labor, 'zoran', 'Front suspension inspection', 0.5],
            [JobItemType::Part, 'zoran', 'SL-4301', 2],
            [JobItemType::Labor, 'zoran', 'Replace front stabilizer links', 1],
        ]],
        ['plate' => 'BG-650-BV', 'day' => 52, 'at' => '13:05', 'booked' => '13:00', 'by' => 'jelena', 'mileage' => 288100, 'complaint' => 'Timing belt due per the service schedule.', 'outcome' => 'paid', 'paid_after' => 2, 'lines' => [
            [JobItemType::Labor, 'dragan', 'Timing belt replacement', 3.5],
            [JobItemType::Part, 'dragan', 'TB-3301', 1],
            [JobItemType::Part, 'dragan', 'AC-6101', 3],
        ]],
        ['plate' => 'SM-045-VK', 'day' => 50, 'at' => '09:30', 'booked' => null, 'by' => 'ana', 'mileage' => 98650, 'complaint' => "Won't start in the morning; battery light flickers.", 'outcome' => 'paid', 'lines' => [
            [JobItemType::Labor, 'stefan', 'Battery and charging system test', 0.5],
            [JobItemType::Part, 'stefan', 'BA-5001', 1],
            [JobItemType::Labor, 'stefan', 'Battery replacement', 0.5],
        ]],
        ['plate' => 'BG-440-FC', 'day' => 49, 'at' => '08:05', 'booked' => '08:00', 'by' => 'jelena', 'mileage' => 177300, 'complaint' => 'Annual service; wipers smear.', 'outcome' => 'paid', 'paid_after' => 5, 'lines' => [
            [JobItemType::Labor, 'nikola', 'Annual service', 1.5],
            [JobItemType::Part, 'nikola', 'OF-1010', 1],
            [JobItemType::Part, 'nikola', 'EO-6001', 5],
            [JobItemType::Part, 'nikola', 'CF-1201', 1],
            [JobItemType::Part, 'nikola', 'WB-7001', 2],
        ]],
        ['plate' => 'KG-088-MP', 'day' => 47, 'at' => '11:00', 'booked' => null, 'by' => 'ana', 'mileage' => 220150, 'complaint' => 'Grinding noise from the rear when braking.', 'outcome' => 'paid', 'discounts' => [2 => '100.00'], 'lines' => [
            [JobItemType::Labor, 'zoran', 'Rear brake pads replacement', 1],
            [JobItemType::Part, 'zoran', 'BP-2003', 1],
            [JobItemType::Part, 'zoran', 'BS-2301', 1],
        ]],
        ['plate' => 'BG-377-ST', 'day' => 45, 'at' => '08:50', 'booked' => '08:30', 'by' => 'jelena', 'mileage' => 301900, 'complaint' => 'Coolant leak; temperature climbs in traffic.', 'outcome' => 'paid', 'paid_after' => 4, 'wait' => [1, 2], 'lines' => [
            [JobItemType::Labor, 'dragan', 'Cooling system pressure test', 0.5],
            [JobItemType::Part, 'dragan', 'WP-3401', 1],
            [JobItemType::Part, 'dragan', 'TS-3601', 1],
            [JobItemType::Part, 'dragan', 'AC-6101', 5],
            [JobItemType::Labor, 'dragan', 'Water pump and thermostat replacement', 2.5],
        ]],
        ['plate' => 'BG-2102-BK', 'day' => 44, 'at' => '09:15', 'booked' => null, 'by' => 'jelena', 'mileage' => 229600, 'complaint' => 'Service due; front tyres wearing on the inside edge.', 'outcome' => 'paid', 'paid_after' => 12, 'due' => self::FLEET_DUE_DAYS, 'discounts' => [0 => self::FLEET_LABOR_DISCOUNT, 3 => self::FLEET_LABOR_DISCOUNT], 'lines' => [
            [JobItemType::Labor, 'stefan', 'Oil and filter service', 1],
            [JobItemType::Part, 'stefan', 'OF-1001', 1],
            [JobItemType::Part, 'stefan', 'EO-6001', 5],
            [JobItemType::Labor, 'stefan', 'Replace tie rod ends', 1.5],
            [JobItemType::Part, 'stefan', 'TR-4201', 2],
        ]],
        ['plate' => 'BG-908-IN', 'day' => 42, 'at' => '10:35', 'booked' => '10:30', 'by' => 'ana', 'mileage' => 97100, 'complaint' => 'Air conditioning smells musty.', 'outcome' => 'paid', 'lines' => [
            [JobItemType::Labor, 'nikola', 'Cabin filter replacement and A/C disinfection', 0.5],
            [JobItemType::Part, 'nikola', 'CF-1202', 1],
        ]],
        ['plate' => 'NS-610-TL', 'day' => 40, 'at' => '08:20', 'booked' => null, 'by' => 'jelena', 'mileage' => 159700, 'complaint' => 'Headlight out and a rattle over bumps.', 'outcome' => 'paid', 'paid_after' => 1, 'lines' => [
            [JobItemType::Part, null, 'BL-5101', 2],
            [JobItemType::Labor, 'zoran', 'Replace headlight bulbs', 0.3],
            [JobItemType::Labor, 'zoran', 'Replace front lower control arm', 2],
            [JobItemType::Part, 'zoran', 'CL-4101', 1],
        ]],
        ['plate' => 'BG-529-VK', 'day' => 38, 'at' => '09:05', 'booked' => '09:00', 'by' => 'ana', 'mileage' => 122300, 'complaint' => 'Misfire on acceleration.', 'outcome' => 'paid', 'lines' => [
            [JobItemType::Labor, 'dragan', 'Diagnostics', 0.5],
            [JobItemType::Labor, 'dragan', 'Replace ignition coil and spark plugs', 1],
            [JobItemType::Part, 'dragan', 'IC-3201', 1],
            [JobItemType::Part, 'dragan', 'SP-3001', 4],
        ]],
        ['plate' => 'SM-045-VK', 'day' => 36, 'at' => '08:00', 'booked' => null, 'by' => 'jelena', 'mileage' => 98900, 'complaint' => 'Front brakes noisy.', 'outcome' => 'paid', 'lines' => [], 'template' => [JobTemplateSeeder::FRONT_BRAKE_PADS, 'stefan', null]],
        ['plate' => 'KG-089-MP', 'day' => 35, 'at' => '12:00', 'booked' => null, 'by' => 'ana', 'mileage' => 89100, 'complaint' => 'Oil service.', 'outcome' => 'paid', 'paid_after' => 2, 'lines' => [
            [JobItemType::Labor, 'nikola', 'Oil and filter service', 0.8],
            [JobItemType::Part, 'nikola', 'OF-1001', 1],
            [JobItemType::Part, 'nikola', 'EO-6001', 4],
        ]],
        ['plate' => 'BG-204-SR', 'day' => 33, 'at' => '08:35', 'booked' => '08:30', 'by' => 'jelena', 'mileage' => 105200, 'complaint' => 'Squeaking belt on a cold start.', 'outcome' => 'paid', 'lines' => [
            [JobItemType::Labor, 'zoran', 'Replace auxiliary drive belt', 0.8],
            [JobItemType::Part, 'zoran', 'SB-3501', 1],
        ]],
        ['plate' => 'NS-221-TT', 'day' => 31, 'at' => '09:45', 'booked' => null, 'by' => 'ana', 'mileage' => 214300, 'complaint' => 'Pre-sale inspection.', 'outcome' => 'paid', 'lines' => [
            [JobItemType::Labor, 'dragan', 'Pre-sale inspection', 1],
        ]],
        ['plate' => 'BG-315-DM', 'day' => 30, 'at' => '08:15', 'booked' => null, 'by' => 'jelena', 'mileage' => 259100, 'complaint' => 'Hesitation at low revs; fuel filter overdue.', 'outcome' => 'unpaid', 'lines' => [
            [JobItemType::Labor, 'nikola', 'Fuel filter replacement', 1],
            [JobItemType::Part, 'nikola', 'FF-1301', 1],
        ]],
        ['plate' => 'BG-812-UJ', 'day' => 28, 'at' => '10:00', 'booked' => null, 'by' => 'ana', 'mileage' => 120400, 'complaint' => 'Clunk from the front left when turning.', 'outcome' => 'reissued', 'paid_after' => 1, 'correction' => [0, 2.5, 'Replace front wheel bearing and hub nut'], 'lines' => [
            [JobItemType::Labor, 'zoran', 'Replace front wheel bearing', 1.5],
            [JobItemType::Part, 'zoran', 'WB-4401', 1],
        ]],
        ['plate' => 'BG-2103-BK', 'day' => 26, 'at' => '08:35', 'booked' => '08:30', 'by' => 'jelena', 'mileage' => 188000, 'complaint' => 'Service and brake check.', 'outcome' => 'paid', 'paid_after' => 14, 'due' => self::FLEET_DUE_DAYS, 'discounts' => [0 => self::FLEET_LABOR_DISCOUNT, 3 => self::FLEET_LABOR_DISCOUNT], 'lines' => [
            [JobItemType::Labor, 'stefan', 'Oil and filter service', 1],
            [JobItemType::Part, 'stefan', 'OF-1002', 1],
            [JobItemType::Part, 'stefan', 'EO-6001', 5],
            [JobItemType::Labor, 'stefan', 'Brake inspection', 0.3],
        ]],
        ['plate' => 'BG-051-LS', 'day' => 24, 'at' => '09:30', 'booked' => null, 'by' => 'ana', 'mileage' => 131900, 'complaint' => 'Wipers streak, washer fluid empty, side light out.', 'outcome' => 'paid', 'lines' => [
            [JobItemType::Part, null, 'WB-7002', 2],
            [JobItemType::Part, null, 'WW-6201', 1],
            [JobItemType::Part, null, 'BL-5102', 2],
            [JobItemType::Labor, 'stefan', 'Fit wiper blades and bulbs', 0.3],
        ]],
        ['plate' => 'KV-300-GR', 'day' => 22, 'at' => '13:35', 'booked' => '13:30', 'by' => 'jelena', 'mileage' => 234500, 'complaint' => 'Timing belt interval reached.', 'outcome' => 'paid', 'paid_after' => 1, 'lines' => [
            [JobItemType::Labor, 'dragan', 'Timing belt replacement', 3],
            [JobItemType::Part, 'dragan', 'TB-3301', 1],
            [JobItemType::Part, 'dragan', 'AC-6101', 2],
        ]],
        ['plate' => 'BG-925-TF', 'day' => 20, 'at' => '08:00', 'booked' => null, 'by' => 'ana', 'mileage' => 189000, 'complaint' => 'Front shock absorbers leaking.', 'outcome' => 'voided', 'lines' => [
            [JobItemType::Labor, 'nikola', 'Replace front shock absorbers', 2],
            [JobItemType::Part, 'nikola', 'SA-4001', 2],
        ]],
        ['plate' => 'BG-164-KS', 'day' => 18, 'at' => '09:05', 'booked' => '09:00', 'by' => 'jelena', 'mileage' => 64900, 'complaint' => 'Scheduled hybrid service.', 'outcome' => 'paid', 'lines' => [
            [JobItemType::Labor, 'stefan', 'Hybrid service', 1],
            [JobItemType::Part, 'stefan', 'OF-1003', 1],
            [JobItemType::Part, 'stefan', 'EO-6001', 4],
            [JobItemType::Part, 'stefan', 'AF-1101', 1],
        ]],
        ['plate' => 'SA-417-PM', 'day' => 16, 'at' => '10:15', 'booked' => null, 'by' => 'ana', 'mileage' => 208100, 'complaint' => 'Battery dead; the car was jump-started.', 'outcome' => 'paid', 'paid_after' => 2, 'lines' => [
            [JobItemType::Part, null, 'BA-5002', 1],
            [JobItemType::Labor, 'nikola', 'Battery replacement and charging test', 0.5],
        ]],
        ['plate' => 'NS-221-TT', 'day' => 14, 'at' => '08:40', 'booked' => null, 'by' => 'jelena', 'mileage' => 215800, 'complaint' => 'New owner: full service after purchase.', 'outcome' => 'unpaid', 'lines' => [
            [JobItemType::Labor, 'dragan', 'Full service', 2],
            [JobItemType::Part, 'dragan', 'OF-1003', 1],
            [JobItemType::Part, 'dragan', 'EO-6002', 5],
            [JobItemType::Part, 'dragan', 'AF-1102', 1],
            [JobItemType::Part, 'dragan', 'CF-1201', 1],
        ]],
        ['plate' => 'BG-633-AZ', 'day' => 12, 'at' => '11:00', 'booked' => null, 'by' => 'ana', 'mileage' => 176900, 'complaint' => 'Brake pedal feels soft.', 'outcome' => 'paid', 'lines' => [
            [JobItemType::Labor, 'stefan', 'Brake fluid replacement', 0.8],
            [JobItemType::Part, 'stefan', 'BF-2201', 1],
        ]],
        ['plate' => 'PA-091-MS', 'day' => 10, 'at' => '08:35', 'booked' => '08:30', 'by' => 'jelena', 'mileage' => 98300, 'complaint' => 'Check engine light on.', 'outcome' => 'unpaid', 'lines' => [
            [JobItemType::Labor, 'nikola', 'Diagnostics', 0.5],
            [JobItemType::Labor, 'nikola', 'Replace spark plugs', 0.5],
            [JobItemType::Part, 'nikola', 'SP-3001', 4],
        ]],
        ['plate' => 'BG-2104-BK', 'day' => 8, 'at' => '09:00', 'booked' => null, 'by' => 'ana', 'mileage' => 133900, 'complaint' => 'Rear suspension noise; front discs worn.', 'outcome' => 'cancelled', 'lines' => [
            [JobItemType::Labor, 'stefan', 'Inspection', 0.5],
            [JobItemType::Part, 'stefan', 'BD-2101', 2],
        ]],
        ['plate' => 'BG-118-JD', 'day' => 7, 'at' => '08:20', 'booked' => '08:15', 'by' => 'jelena', 'mileage' => 71900, 'complaint' => 'Yearly service.', 'outcome' => 'completed', 'lines' => [
            [JobItemType::Labor, 'dragan', 'Oil and filter service', 1],
            [JobItemType::Part, 'dragan', 'OF-1001', 1],
            [JobItemType::Part, 'dragan', 'EO-6001', 5],
        ]],
        ['plate' => 'BG-377-ST', 'day' => 6, 'at' => '10:00', 'booked' => null, 'by' => 'ana', 'mileage' => 303400, 'complaint' => 'Turbo whistle and loss of power.', 'outcome' => 'awaiting_parts', 'lines' => [
            [JobItemType::Labor, 'dragan', 'Boost leak test', 1],
        ]],
        ['plate' => 'NI-552-NS', 'day' => 5, 'at' => '08:05', 'booked' => '08:00', 'by' => 'jelena', 'mileage' => 270100, 'complaint' => 'Clutch judder when pulling away.', 'outcome' => 'cancelled', 'lines' => [
            [JobItemType::Labor, 'nikola', 'Clutch inspection', 0.5],
        ]],
        ['plate' => 'BG-650-BV', 'day' => 4, 'at' => '09:30', 'booked' => null, 'by' => 'ana', 'mileage' => 289400, 'complaint' => 'Brake warning light on.', 'outcome' => 'completed', 'discounts' => [0 => '5.00'], 'lines' => [
            [JobItemType::Labor, 'nikola', 'Rear brake pads and sensor replacement', 1.2],
            [JobItemType::Part, 'nikola', 'BP-2003', 1],
            [JobItemType::Part, 'nikola', 'BS-2301', 1],
        ]],
        ['plate' => 'BG-440-FC', 'day' => 3, 'at' => '08:05', 'booked' => '08:00', 'by' => 'jelena', 'mileage' => 183100, 'complaint' => 'Front brakes grinding.', 'outcome' => 'awaiting_parts', 'lines' => [
            [JobItemType::Labor, 'stefan', 'Front brake pads replacement', 1],
            [JobItemType::Part, 'stefan', 'BP-2002', 1],
        ]],
        ['plate' => 'KG-088-MP', 'day' => 2, 'at' => '08:30', 'booked' => null, 'by' => 'ana', 'mileage' => 223800, 'complaint' => 'Service; knocking from the front over bumps.', 'outcome' => 'in_progress', 'lines' => [
            [JobItemType::Labor, 'dragan', 'Oil and filter service', 1],
            [JobItemType::Part, 'dragan', 'OF-1001', 1],
            [JobItemType::Part, 'dragan', 'EO-6001', 5],
        ]],
        ['plate' => 'BG-529-VK', 'day' => 1, 'at' => '09:00', 'booked' => null, 'by' => 'jelena', 'mileage' => 125100, 'complaint' => 'Service.', 'outcome' => 'in_progress', 'lines' => [
            [JobItemType::Labor, 'stefan', 'Oil and filter service', 1],
        ]],
        ['plate' => 'BG-2101-BK', 'day' => 1, 'at' => '14:00', 'booked' => null, 'by' => 'ana', 'mileage' => 247300, 'complaint' => 'Air conditioning not cooling.', 'outcome' => 'in_progress', 'lines' => [
            [JobItemType::Labor, 'nikola', 'A/C diagnostics', 0.5],
        ]],
        ['plate' => 'BG-315-DM', 'day' => 0, 'at' => '08:05', 'booked' => '08:00', 'by' => 'jelena', 'mileage' => 261000, 'complaint' => 'Front suspension knocking.', 'outcome' => 'received', 'lines' => []],
        ['plate' => 'BG-204-SR', 'day' => 0, 'at' => '08:40', 'booked' => null, 'by' => 'ana', 'mileage' => 106900, 'complaint' => 'Rear light not working.', 'outcome' => 'received', 'lines' => []],
        ['plate' => 'NS-747-DM', 'day' => 0, 'at' => '09:20', 'booked' => null, 'by' => 'jelena', 'mileage' => 245300, 'complaint' => 'Oil leak under the engine.', 'outcome' => 'in_progress', 'lines' => [
            [JobItemType::Labor, 'stefan', 'Leak inspection', 0.5],
        ]],
        ['plate' => 'VA-233-GL', 'day' => 0, 'at' => '10:10', 'booked' => null, 'by' => 'ana', 'mileage' => 135200, 'complaint' => 'Exhaust rattle at idle.', 'outcome' => 'received', 'lines' => []],
    ];

    /**
     * plate, days ago (negative = upcoming), time, duration minutes, booked by, outcome, notes
     *
     * @var list<array{string, int, string, int, string, string, ?string}>
     */
    private const array APPOINTMENTS = [
        ['BG-2102-BK', 20, '11:00', 60, 'jelena', 'no_show', 'Fleet: brake check.'],
        ['SM-045-VK', 16, '09:00', 60, 'jelena', 'cancelled', 'Customer is selling the car.'],
        ['BG-812-UJ', 9, '15:00', 60, 'ana', 'no_show', null],
        ['NS-610-TL', 0, '10:30', 90, 'jelena', 'booked', 'Annual service.'],
        ['KG-089-MP', 0, '14:00', 60, 'ana', 'booked', null],
        ['BG-925-TF', 0, '16:00', 30, 'jelena', 'booked', 'Customer wants to discuss the voided invoice.'],
        ['BG-2103-BK', -1, '08:00', 60, 'jelena', 'booked', 'Fleet: tyre change.'],
        ['BG-2102-BK', -1, '08:00', 120, 'jelena', 'booked', 'Fleet: service and brake check.'],
        ['BG-908-IN', -2, '09:30', 60, 'ana', 'booked', null],
        ['BG-164-KS', -3, '10:00', 60, 'ana', 'cancelled', 'Customer called to cancel; will rebook.'],
        ['PA-091-MS', -4, '11:00', 60, 'jelena', 'booked', 'Follow-up after the spark plugs.'],
        ['BG-530-VK', -7, '08:30', 240, 'ana', 'booked', 'DSG service; car needed back the same day.'],
        ['BG-633-AZ', -9, '10:00', 60, 'jelena', 'booked', null],
        ['KV-300-GR', -13, '13:00', 60, 'ana', 'booked', null],
    ];

    /**
     * days ago, time, recorded by, supplier (null = manual adjustment), note, quantities by SKU
     *
     * @var list<array{int, string, string, ?string, ?string, array<string, int>}>
     */
    private const array STOCK_MOVEMENTS = [
        [50, '08:00', 'jelena', 'Delta Auto Delovi', null, ['EO-6001' => 40, 'OF-1001' => 10]],
        [43, '07:45', 'ana', 'Delta Auto Delovi', 'Special order for the Passat.', ['WP-3401' => 2]],
        [35, '16:30', 'ana', null, 'Two bulbs broken in storage.', ['BL-5101' => -2]],
        [30, '14:00', 'jelena', 'Euro Kontakt Beograd', null, ['BP-2001' => 4, 'BP-2002' => 4, 'BD-2101' => 6]],
        [21, '15:00', 'ana', 'Auto Kuća Novi Sad', null, ['SP-3001' => 16, 'BL-5101' => 20]],
        [18, '17:00', 'jelena', null, 'Stock count correction.', ['EO-6001' => -1]],
        [10, '13:00', 'jelena', 'Euro Kontakt Beograd', null, ['EO-6002' => 20]],
        [2, '12:00', 'ana', 'Delta Auto Delovi', null, ['CF-1201' => 6]],
    ];

    /** @var list<array{CarbonImmutable, int, Closure(): void}> */
    private array $timeline = [];

    public function __construct(
        private readonly AppointmentService $appointments,
        private readonly JobService $jobs,
        private readonly JobItemService $items,
        private readonly InvoiceService $invoices,
        private readonly StockService $stock,
        private readonly SupplierService $suppliers,
        private readonly VehicleService $vehicles,
        private readonly CustomerService $customers,
        private readonly StaffService $staffService,
    ) {}

    protected function populate(): void
    {
        foreach (self::JOBS as $spec) {
            $this->scheduleJob($spec);
        }

        foreach (self::APPOINTMENTS as $spec) {
            $this->scheduleAppointment(...$spec);
        }

        foreach (self::STOCK_MOVEMENTS as $spec) {
            $this->scheduleStockMovement(...$spec);
        }

        $this->scheduleRecordChanges();

        $this->play();
    }

    /**
     * @param  Closure(): void  $step
     */
    private function at(CarbonImmutable $moment, Closure $step): void
    {
        $this->timeline[] = [$moment, count($this->timeline), $step];
    }

    private function play(): void
    {
        usort($this->timeline, fn (array $a, array $b): int => [$a[0]->getTimestamp(), $a[1]] <=> [$b[0]->getTimestamp(), $b[1]]);

        foreach ($this->timeline as [$moment, , $step]) {
            $this->travelTo($moment);
            $step();
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function scheduleJob(array $spec): void
    {
        $job = null;
        $items = [];
        $invoice = null;
        $desk = $spec['by'];
        $opened = $this->daysAgo($spec['day'], $spec['at']);
        $intake = ['complaint' => $spec['complaint'], 'mileage_in' => $spec['mileage']];

        if ($spec['booked'] === null) {
            $this->at($opened, function () use (&$job, $spec, $desk, $intake): void {
                $job = $this->jobs->createWalkIn($this->vehicle($spec['plate']), $this->staff($desk), $intake);
            });
        } else {
            $appointment = null;
            $scheduled = $this->daysAgo($spec['day'], $spec['booked']);

            $this->at($scheduled->subDays(3)->setTime(11, 0), function () use (&$appointment, $spec, $desk, $scheduled): void {
                $appointment = $this->appointments->create($this->vehicle($spec['plate']), $this->staff($desk), [
                    'scheduled_at' => $scheduled,
                    'duration_minutes' => 60,
                ]);
            });

            $this->at($opened, function () use (&$job, &$appointment, $desk, $intake): void {
                $job = $this->jobs->createFromAppointment($appointment, $this->staff($desk), $intake);
            });
        }

        $outcome = $spec['outcome'];

        if ($outcome === 'received') {
            return;
        }

        $cursor = $opened->addMinutes(20);
        $this->at($cursor, function () use (&$job): void {
            $this->jobs->start($job);
        });

        foreach ($spec['lines'] as $index => $line) {
            if (isset($spec['wait']) && $spec['wait'][0] === $index) {
                $cursor = $cursor->addMinutes(10);
                $this->at($cursor, function () use (&$job): void {
                    $this->jobs->pause($job);
                });

                $cursor = $cursor->addDays($spec['wait'][1])->setTime(8, 30);
                $this->at($cursor, function () use (&$job): void {
                    $this->jobs->resume($job);
                });
            }

            $cursor = $cursor->addMinutes(35);
            $this->at($cursor, function () use (&$job, &$items, $line, $desk): void {
                $items[] = $this->addLine($job, $line, $desk);
            });
        }

        if (isset($spec['template'])) {
            $cursor = $cursor->addMinutes(35);
            $this->at($cursor, function () use (&$job, &$items, $spec): void {
                array_push($items, ...$this->applyTemplate($job, ...$spec['template'])->all());
            });
        }

        if ($outcome === 'in_progress') {
            return;
        }

        if ($outcome === 'awaiting_parts') {
            $this->at($cursor->addMinutes(15), function () use (&$job): void {
                $this->jobs->pause($job);
            });

            return;
        }

        if ($outcome === 'cancelled') {
            $this->at($cursor->addHour(), function () use (&$job, $desk): void {
                $this->jobs->cancel($job, $this->staff($desk));
            });

            return;
        }

        $cursor = $cursor->addMinutes(30);
        $this->at($cursor, function () use (&$job): void {
            $this->jobs->complete($job);
        });

        if (isset($spec['discounts'])) {
            $this->at($cursor->addMinutes(10), function () use (&$items, $spec): void {
                foreach ($spec['discounts'] as $lineIndex => $percent) {
                    $this->items->setDiscount($items[$lineIndex], $percent);
                }
            });
        }

        if ($outcome === 'completed') {
            return;
        }

        $dueDays = $spec['due'] ?? self::DUE_DAYS;
        $issue = function () use (&$job, &$invoice, $desk, $dueDays): void {
            $invoice = $this->invoices->issue($job, $this->staff($desk), now()->addDays($dueDays));
        };

        $cursor = $cursor->addMinutes(20);
        $this->at($cursor, $issue);

        if ($outcome === 'unpaid') {
            return;
        }

        if ($outcome === 'voided') {
            $this->at($cursor->addDays(2)->setTime(10, 0), function () use (&$invoice): void {
                $this->invoices->void($invoice, $this->staff(self::VOIDING_ADMIN));
            });

            return;
        }

        if ($outcome === 'reissued') {
            $cursor = $cursor->addDay()->setTime(9, 0);
            $this->at($cursor, function () use (&$invoice): void {
                $this->invoices->void($invoice, $this->staff(self::VOIDING_ADMIN));
            });

            [$lineIndex, $quantity, $description] = $spec['correction'];

            $cursor = $cursor->addMinutes(10);
            $this->at($cursor, function () use (&$job, &$items, $desk, $lineIndex, $quantity, $description): void {
                $job->refresh();
                $this->jobs->reopen($job);

                $this->items->update($items[$lineIndex], $this->staff($desk), [
                    'description' => $description,
                    'quantity' => $quantity,
                    'user_id' => $items[$lineIndex]->user_id,
                ]);

                $this->jobs->complete($job);
            });

            $cursor = $cursor->addMinutes(20);
            $this->at($cursor, $issue);
        }

        $paidAfter = $spec['paid_after'] ?? 0;
        $paidAt = $paidAfter === 0 ? $cursor->addMinutes(15) : $cursor->addDays($paidAfter)->setTime(10, 0);
        $method = match (true) {
            isset($spec['due']) => PaymentMethod::BankTransfer,
            $paidAfter === 0 => PaymentMethod::Cash,
            default => PaymentMethod::Card,
        };

        $this->at($paidAt, function () use (&$invoice, $method): void {
            $this->invoices->markPaid($invoice, $method);
        });
    }

    /**
     * @param  array{JobItemType, ?string, string, int|float}  $line
     */
    private function addLine(Job $job, array $line, string $desk): JobItem
    {
        [$type, $mechanic, $subject, $quantity] = $line;

        if ($type === JobItemType::Labor) {
            return $this->items->addLabor($job, $this->staff($mechanic), [
                'description' => $subject,
                'quantity' => $quantity,
            ]);
        }

        return $this->items->addPart($job, $this->part($subject), $this->staff($mechanic ?? $desk), [
            'quantity' => $quantity,
        ]);
    }

    private function scheduleAppointment(string $plate, int $day, string $time, int $duration, string $desk, string $outcome, ?string $notes): void
    {
        $appointment = null;
        $scheduled = $this->daysAgo($day, $time);

        $this->at($scheduled->subDays(3)->setTime(11, 0), function () use (&$appointment, $plate, $desk, $scheduled, $duration, $notes): void {
            $appointment = $this->appointments->create($this->vehicle($plate), $this->staff($desk), [
                'scheduled_at' => $scheduled,
                'duration_minutes' => $duration,
                'notes' => $notes,
            ]);
        });

        if ($outcome === 'no_show') {
            $this->at($scheduled->addHours(2), function () use (&$appointment): void {
                $this->appointments->markNoShow($appointment);
            });
        }

        if ($outcome === 'cancelled') {
            $this->at($scheduled->subDay()->setTime(10, 0), function () use (&$appointment): void {
                $this->appointments->cancel($appointment);
            });
        }
    }

    /**
     * @param  array<string, int>  $quantities
     */
    private function scheduleStockMovement(int $day, string $time, string $handle, ?string $supplier, ?string $note, array $quantities): void
    {
        $this->at($this->daysAgo($day, $time), function () use ($handle, $supplier, $note, $quantities): void {
            $resolved = $this->suppliers->resolveByName($supplier);
            $reason = $resolved === null ? StockMovementReason::ManualAdjustment : StockMovementReason::Restock;

            foreach ($quantities as $sku => $quantity) {
                $this->stock->record($this->part($sku), $quantity, $reason, $this->staff($handle), note: $note, supplier: $resolved);
            }
        });
    }

    private function scheduleRecordChanges(): void
    {
        $this->at($this->daysAgo(34, '12:00'), function (): void {
            $this->vehicles->delete($this->vehicle('BG-771-AN'));
        });

        $this->at($this->daysAgo(28, '16:00'), function (): void {
            $this->vehicles->reassign($this->vehicle('NS-221-TT'), $this->customer('Ivana Nikolić'));
        });

        $this->at($this->daysAgo(25, '17:00'), function (): void {
            $this->staffService->offboard($this->staff('zoran'));
        });

        $this->at($this->daysAgo(15, '17:00'), function (): void {
            $this->vehicles->delete($this->vehicle('SM-045-VK'));
            $this->customers->delete($this->customer('Vesna Kostić'));
        });
    }

    private function vehicle(string $plate): Vehicle
    {
        return Vehicle::query()->where('registration_plate', $plate)->firstOrFail();
    }

    private function customer(string $name): Customer
    {
        return Customer::query()->where('name', $name)->firstOrFail();
    }

    private function part(string $sku): Part
    {
        return Part::query()->where('sku', $sku)->firstOrFail();
    }

    /**
     * @return Collection<int, JobItem>
     */
    private function applyTemplate(Job $job, string $name, string $by, ?string $mechanic): Collection
    {
        return $this->items->applyTemplate(
            $job,
            JobTemplate::query()->where('name', $name)->firstOrFail(),
            $this->staff($by),
            $mechanic === null ? null : $this->staff($mechanic)->getKey(),
        );
    }
}
