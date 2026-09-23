<?php

namespace Database\Seeders\Demo;

use App\Services\CustomerService;
use App\Services\VehicleService;

class CustomerSeeder extends DemoSeeder
{
    /** @var array<string, string> */
    private const array MANUFACTURER_CODES = [
        'Volkswagen' => 'WVW',
        'Škoda' => 'TMB',
        'Opel' => 'W0L',
        'Ford' => 'WF0',
        'Renault' => 'VF1',
        'Peugeot' => 'VF3',
        'Fiat' => 'ZFA',
        'Toyota' => 'SB1',
        'BMW' => 'WBA',
        'Audi' => 'WAU',
        'Mercedes-Benz' => 'WDD',
        'Dacia' => 'UU1',
        'Hyundai' => 'TMA',
        'Kia' => 'U5Y',
    ];

    /**
     * Each vehicle: plate, make, model, year, engine, last known mileage, notes.
     *
     * @var list<array{name: string, phone: string, email: ?string, address: ?string, notes: ?string, vehicles: list<array{string, string, string, int, string, int, ?string}>}>
     */
    public const array CUSTOMERS = [
        ['name' => 'Marko Petrov', 'phone' => '+381 64 123 4567', 'email' => 'marko.petrov@example.com', 'address' => 'Bulevar kralja Aleksandra 73, Beograd', 'notes' => null, 'vehicles' => [
            ['BG-123-AB', 'Volkswagen', 'Golf IV', 2002, '1.9 TDI', 183900, null],
        ]],
        ['name' => 'Jovan Ilić', 'phone' => '+381 63 881 2201', 'email' => 'jovan.ilic@example.com', 'address' => 'Futoška 14, Novi Sad', 'notes' => null, 'vehicles' => [
            ['NS-221-TT', 'Opel', 'Astra H', 2007, '1.7 CDTI', 212400, null],
        ]],
        ['name' => 'Ivana Nikolić', 'phone' => '+381 65 334 9087', 'email' => null, 'address' => 'Cara Dušana 21, Zemun', 'notes' => null, 'vehicles' => [
            ['BG-908-IN', 'Toyota', 'Corolla', 2016, '1.6 Valvematic', 96200, null],
        ]],
        ['name' => 'Aleksandar Nikolić', 'phone' => '+381 64 555 0192', 'email' => 'aleksandar.nikolic@example.com', 'address' => 'Vojvode Stepe 250, Beograd', 'notes' => 'Call before any extra work over 10,000.', 'vehicles' => [
            ['BG-440-FC', 'Ford', 'Focus', 2011, '1.6 TDCi', 176500, null],
            ['BG-771-AN', 'Renault', 'Clio', 2009, '1.2 16V', 142000, null],
        ]],
        ['name' => 'Vesna Kostić', 'phone' => '+381 62 700 1133', 'email' => null, 'address' => 'Karađorđeva 5, Smederevo', 'notes' => null, 'vehicles' => [
            ['SM-045-VK', 'Zastava', 'Yugo Koral', 1998, '1.1', 98400, 'No VIN plate; chassis number on the title only.'],
        ]],
        ['name' => 'Kurirska služba Brzi d.o.o.', 'phone' => '+381 11 404 2020', 'email' => 'servis@brzi-kurir.example.com', 'address' => 'Autoput za Zagreb 18, Novi Beograd', 'notes' => 'Fleet account. Fleet manager: Petar Lazić, ext. 12.', 'vehicles' => [
            ['BG-2101-BK', 'Fiat', 'Doblò', 2018, '1.6 MultiJet', 241000, 'Fleet van 1'],
            ['BG-2102-BK', 'Fiat', 'Doblò', 2018, '1.6 MultiJet', 228700, 'Fleet van 2'],
            ['BG-2103-BK', 'Renault', 'Kangoo', 2019, '1.5 dCi', 187300, 'Fleet van 3'],
            ['BG-2104-BK', 'Dacia', 'Dokker', 2020, '1.5 dCi', 132800, 'Fleet van 4'],
        ]],
        ['name' => 'Dragana Milošević', 'phone' => '+381 60 212 7788', 'email' => 'dragana.m@example.com', 'address' => 'Obilićev venac 2, Beograd', 'notes' => null, 'vehicles' => [
            ['BG-315-DM', 'Škoda', 'Octavia II', 2010, '1.9 TDI', 256700, null],
        ]],
        ['name' => 'Nenad Stojanović', 'phone' => '+381 64 999 1234', 'email' => null, 'address' => 'Nemanjina 40, Niš', 'notes' => null, 'vehicles' => [
            ['NI-552-NS', 'BMW', '320d (E90)', 2008, '2.0d', 268000, null],
        ]],
        ['name' => 'Tamara Lukić', 'phone' => '+381 63 440 5566', 'email' => 'tamara.lukic@example.com', 'address' => 'Bulevar oslobođenja 101, Novi Sad', 'notes' => null, 'vehicles' => [
            ['NS-610-TL', 'Peugeot', '308', 2014, '1.6 HDi', 158900, null],
        ]],
        ['name' => 'Miloš Pavlović', 'phone' => '+381 66 123 0099', 'email' => 'milos.pavlovic@example.com', 'address' => 'Kralja Petra 12, Kragujevac', 'notes' => null, 'vehicles' => [
            ['KG-088-MP', 'Audi', 'A4 (B8)', 2012, '2.0 TDI', 219300, null],
            ['KG-089-MP', 'Volkswagen', 'Polo', 2015, '1.2 TSI', 88400, null],
        ]],
        ['name' => 'Snežana Radović', 'phone' => '+381 62 818 4040', 'email' => null, 'address' => 'Ustanička 125, Beograd', 'notes' => null, 'vehicles' => [
            ['BG-204-SR', 'Hyundai', 'i30', 2017, '1.4 CRDi', 104500, null],
        ]],
        ['name' => 'Bojan Vasić', 'phone' => '+381 64 707 3030', 'email' => 'bojan.vasic@example.com', 'address' => 'Bulevar Mihajla Pupina 10, Novi Beograd', 'notes' => null, 'vehicles' => [
            ['BG-650-BV', 'Mercedes-Benz', 'C 220 CDI (W204)', 2011, '2.1 CDI', 287000, null],
        ]],
        ['name' => 'Jelena Đukić', 'phone' => '+381 65 222 1188', 'email' => 'jelena.djukic@example.com', 'address' => null, 'notes' => null, 'vehicles' => [
            ['BG-118-JD', 'Kia', 'Ceed', 2019, '1.6 CRDi', 71200, null],
        ]],
        ['name' => 'Stefan Todorović', 'phone' => '+381 61 303 4455', 'email' => null, 'address' => 'Topolska 8, Beograd', 'notes' => null, 'vehicles' => [
            ['BG-377-ST', 'Volkswagen', 'Passat B6', 2008, '2.0 TDI', 301200, null],
        ]],
        ['name' => 'Maja Simić', 'phone' => '+381 63 191 2828', 'email' => 'maja.simic@example.com', 'address' => 'Železnička 3, Pančevo', 'notes' => null, 'vehicles' => [
            ['PA-091-MS', 'Dacia', 'Sandero', 2016, '1.2 16V', 97600, null],
        ]],
        ['name' => 'Vladimir Kovačević', 'phone' => '+381 64 646 1010', 'email' => 'vladimir.kovacevic@example.com', 'address' => 'Požeška 60, Beograd', 'notes' => null, 'vehicles' => [
            ['BG-529-VK', 'Škoda', 'Fabia', 2013, '1.2 TSI', 121800, null],
            ['BG-530-VK', 'Škoda', 'Superb', 2017, '2.0 TDI', 198400, null],
        ]],
        ['name' => 'Gordana Lazić', 'phone' => '+381 60 505 7070', 'email' => null, 'address' => 'Svetog Save 18, Valjevo', 'notes' => null, 'vehicles' => [
            ['VA-233-GL', 'Opel', 'Corsa D', 2010, '1.2 16V', 134500, null],
        ]],
        ['name' => 'Uroš Janković', 'phone' => '+381 69 414 2323', 'email' => 'uros.jankovic@example.com', 'address' => 'Bulevar despota Stefana 55, Beograd', 'notes' => null, 'vehicles' => [
            ['BG-812-UJ', 'Ford', 'Fiesta', 2012, '1.25 Duratec', 119700, null],
        ]],
        ['name' => 'Katarina Stevanović', 'phone' => '+381 65 876 5432', 'email' => 'katarina.s@example.com', 'address' => 'Makenzijeva 31, Beograd', 'notes' => null, 'vehicles' => [
            ['BG-164-KS', 'Toyota', 'Yaris', 2018, '1.5 Hybrid', 64300, null],
        ]],
        ['name' => 'Dejan Marić', 'phone' => '+381 64 313 6060', 'email' => null, 'address' => 'Rumenački put 17, Novi Sad', 'notes' => null, 'vehicles' => [
            ['NS-747-DM', 'Volkswagen', 'Golf V', 2006, '1.9 TDI', 244600, null],
        ]],
        ['name' => 'Ljiljana Savić', 'phone' => '+381 63 727 1515', 'email' => 'ljiljana.savic@example.com', 'address' => 'Kneza Mihaila 9, Beograd', 'notes' => null, 'vehicles' => [
            ['BG-051-LS', 'Fiat', 'Punto', 2011, '1.4 8V', 131200, null],
        ]],
        ['name' => 'Goran Ristić', 'phone' => '+381 62 929 4747', 'email' => null, 'address' => 'Save Kovačevića 44, Kraljevo', 'notes' => null, 'vehicles' => [
            ['KV-300-GR', 'Opel', 'Insignia', 2013, '2.0 CDTI', 233900, null],
        ]],
        ['name' => 'Teodora Filipović', 'phone' => '+381 60 616 8383', 'email' => 'teodora.filipovic@example.com', 'address' => 'Cvijićeva 88, Beograd', 'notes' => null, 'vehicles' => [
            ['BG-925-TF', 'Renault', 'Mégane III', 2012, '1.5 dCi', 188200, null],
        ]],
        ['name' => 'Petar Mitrović', 'phone' => '+381 64 858 9191', 'email' => 'petar.mitrovic@example.com', 'address' => 'Trg oslobođenja 4, Šabac', 'notes' => null, 'vehicles' => [
            ['SA-417-PM', 'Volkswagen', 'Caddy', 2014, '1.6 TDI', 207400, null],
        ]],
        ['name' => 'Aleksa Živković', 'phone' => '+381 61 777 2424', 'email' => null, 'address' => 'Omladinskih brigada 90, Novi Beograd', 'notes' => null, 'vehicles' => [
            ['BG-633-AZ', 'Peugeot', '207', 2009, '1.4 HDi', 176100, null],
        ]],
        ['name' => 'Sanja Obradović', 'phone' => '+381 65 505 0550', 'email' => 'sanja.obradovic@example.com', 'address' => 'Bulevar kralja Aleksandra 190, Beograd', 'notes' => 'Registered by phone; car details to follow.', 'vehicles' => []],
    ];

    public function __construct(
        private readonly CustomerService $customers,
        private readonly VehicleService $vehicles,
    ) {}

    protected function populate(): void
    {
        $serial = 0;

        foreach (self::CUSTOMERS as $index => $data) {
            $this->travelTo($this->daysAgo(110 - $index * 2, '11:30'));

            $customer = $this->customers->create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'notes' => $data['notes'],
            ]);

            foreach ($data['vehicles'] as [$plate, $make, $model, $year, $engine, $mileage, $notes]) {
                $serial++;

                $this->vehicles->create($customer, [
                    'registration_plate' => $plate,
                    'make' => $make,
                    'model' => $model,
                    'year' => $year,
                    'vin' => $this->vin($make, $year, $serial),
                    'engine' => $engine,
                    'mileage' => $mileage,
                    'notes' => $notes,
                ]);
            }
        }
    }

    private function vin(string $make, int $year, int $serial): ?string
    {
        $code = self::MANUFACTURER_CODES[$make] ?? null;

        return $code === null ? null : sprintf('%sZZZ%02d%09d', $code, $year % 100, 104729 * $serial);
    }
}
