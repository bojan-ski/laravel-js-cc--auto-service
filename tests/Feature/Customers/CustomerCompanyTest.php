<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Services\TaxIdChecksum;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerCompanyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const VALID_TAX_ID = '101234569';

    private function actingAsReceptionist(): static
    {
        return $this->actingAs(User::factory()->receptionist()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Kurirska služba d.o.o.',
            'phone' => '011 404 2020',
            ...$overrides,
        ];
    }

    private function store(array $overrides): TestResponse
    {
        return $this->actingAsReceptionist()->post(route('customers.store'), $this->payload($overrides));
    }

    /**
     * @return list<string>
     */
    private function errors(TestResponse $response, string $field): array
    {
        $response->assertRedirect();

        return app('session.store')->get('errors')['default']['messages'][$field] ?? [];
    }

    public function test_the_columns_are_nullable_strings_placed_after_the_address(): void
    {
        $listing = Schema::getColumnListing('customers');
        $address = array_search('address', $listing, true);

        $this->assertSame(['tax_id', 'registration_number'], array_slice($listing, $address + 1, 2));

        $columns = collect(Schema::getColumns('customers'))->keyBy('name');

        $this->assertSame('varchar(9)', $columns['tax_id']['type']);
        $this->assertSame('varchar(8)', $columns['registration_number']['type']);
        $this->assertTrue($columns['tax_id']['nullable']);
        $this->assertTrue($columns['registration_number']['nullable']);
    }

    public function test_the_tax_id_is_not_unique(): void
    {
        Customer::factory()->create(['tax_id' => self::VALID_TAX_ID, 'registration_number' => '12345678']);

        $this->store(['tax_id' => self::VALID_TAX_ID, 'registration_number' => '87654321'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Customer::query()->where('tax_id', self::VALID_TAX_ID)->count());
    }

    public function test_a_customer_with_a_pib_and_an_mb_is_a_company(): void
    {
        $this->store(['tax_id' => self::VALID_TAX_ID, 'registration_number' => '01234567'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $customer = Customer::query()->sole();

        $this->assertTrue($customer->isCompany());
        $this->assertSame(self::VALID_TAX_ID, $customer->tax_id);
        $this->assertSame('01234567', $customer->registration_number, 'An MB may begin with 0, so it is a string.');
    }

    public function test_a_customer_without_a_pib_is_private(): void
    {
        $this->store(['tax_id' => '', 'registration_number' => ''])->assertSessionHasNoErrors();

        $customer = Customer::query()->sole();

        $this->assertFalse($customer->isCompany());
        $this->assertNull($customer->tax_id);
        $this->assertNull($customer->registration_number);
    }

    public function test_whitespace_inside_either_number_is_removed(): void
    {
        $this->store(['tax_id' => ' 101 234 569 ', 'registration_number' => "0123\u{00A0}4567"])
            ->assertSessionHasNoErrors();

        $customer = Customer::query()->sole();

        $this->assertSame(self::VALID_TAX_ID, $customer->tax_id);
        $this->assertSame('01234567', $customer->registration_number);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function wrongLengthOrNonDigitTaxIds(): array
    {
        return [
            'eight digits' => ['10123456'],
            'ten digits' => ['1012345690'],
            'a letter' => ['10123456A'],
            'dashes are never stripped' => ['101-234-569'],
            'dots are never stripped' => ['101.234.569'],
        ];
    }

    #[DataProvider('wrongLengthOrNonDigitTaxIds')]
    public function test_a_pib_that_is_not_nine_digits_reports_only_the_digits_message(string $taxId): void
    {
        $response = $this->store(['tax_id' => $taxId, 'registration_number' => '12345678']);

        $this->assertSame([__('customers.validation.tax_id_digits')], $this->errors($response, 'tax_id'));
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_a_pib_with_a_wrong_check_digit_reports_only_the_checksum_message(): void
    {
        $response = $this->store(['tax_id' => '101234568', 'registration_number' => '12345678']);

        $this->assertSame([__('customers.validation.tax_id_checksum')], $this->errors($response, 'tax_id'));
        $this->assertDatabaseCount('customers', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badRegistrationNumbers(): array
    {
        return [
            'seven digits' => ['1234567'],
            'nine digits' => ['123456789'],
            'a letter' => ['1234567B'],
        ];
    }

    #[DataProvider('badRegistrationNumbers')]
    public function test_an_mb_that_is_not_eight_digits_is_refused(string $registrationNumber): void
    {
        $response = $this->store(['tax_id' => self::VALID_TAX_ID, 'registration_number' => $registrationNumber]);

        $this->assertSame([__('customers.validation.registration_number_digits')], $this->errors($response, 'registration_number'));
    }

    public function test_a_pib_without_an_mb_is_refused(): void
    {
        $response = $this->store(['tax_id' => self::VALID_TAX_ID, 'registration_number' => '']);

        $this->assertSame([__('customers.validation.registration_number_required')], $this->errors($response, 'registration_number'));
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_an_mb_without_a_pib_key_is_refused(): void
    {
        $response = $this->store(['registration_number' => '12345678']);

        $this->assertSame([__('customers.validation.registration_number_without_tax_id')], $this->errors($response, 'registration_number'));
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_an_mb_with_an_empty_string_pib_is_refused(): void
    {
        $response = $this->store(['tax_id' => '', 'registration_number' => '12345678']);

        $this->assertSame([__('customers.validation.registration_number_without_tax_id')], $this->errors($response, 'registration_number'));
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_an_mb_with_a_whitespace_only_pib_is_refused(): void
    {
        $response = $this->store(['tax_id' => '   ', 'registration_number' => '12345678']);

        $this->assertSame([__('customers.validation.registration_number_without_tax_id')], $this->errors($response, 'registration_number'));
    }

    public function test_clearing_both_fields_makes_a_company_private_again(): void
    {
        $customer = Customer::factory()->company()->create();

        $this->actingAsReceptionist()
            ->put(route('customers.update', $customer), [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'tax_id' => '',
                'registration_number' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $customer->refresh();

        $this->assertFalse($customer->isCompany());
        $this->assertNull($customer->registration_number);
    }

    public function test_the_form_groups_both_fields_under_a_described_company_fieldset(): void
    {
        $customer = Customer::factory()->company()->create();

        $html = $this->actingAsReceptionist()->get(route('customers.edit', $customer))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<fieldset[^>]*>\s*<legend[^>]*>\s*'.preg_quote(e(__('customers.form.company')), '/').'\s*<\/legend>/',
            $html,
        );
        $this->assertMatchesRegularExpression('/<p id="company-hint"[^>]*>'.preg_quote(e(__('customers.form.company_hint')), '/').'<\/p>/', $html);

        foreach (['tax_id' => $customer->tax_id, 'registration_number' => $customer->registration_number] as $field => $value) {
            $this->assertMatchesRegularExpression('/<label for="'.$field.'"[^>]*>/', $html);
            $this->assertMatchesRegularExpression('/<input\s+id="'.$field.'"\s+name="'.$field.'"\s+type="text"\s+value="'.$value.'"[^>]*inputmode="numeric"/', $html);
            $this->assertMatchesRegularExpression(
                '/<input\s+id="'.$field.'"[^>]*aria-describedby="company-hint"/',
                $html,
                "Tabbing straight into {$field} must announce the pairing hint.",
            );
        }
    }

    public function test_a_rejected_pib_renders_its_error_on_the_field(): void
    {
        $this->actingAsReceptionist()
            ->from(route('customers.create'))
            ->post(route('customers.store'), $this->payload(['tax_id' => '101234568', 'registration_number' => '12345678']))
            ->assertRedirect(route('customers.create'));

        $html = $this->get(route('customers.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input\s+id="tax_id"[^>]*aria-invalid="true" aria-describedby="tax_id-error"/', $html);
        $this->assertStringContainsString('id="tax_id-error"', $html);
        $this->assertStringContainsString(e(__('customers.validation.tax_id_checksum')), $html);
        $this->assertDoesNotMatchRegularExpression('/<input\s+id="registration_number"[^>]*aria-invalid/', $html);

        preg_match('/<input\s+id="tax_id"[^>]*>/', $html, $taxId);
        $this->assertSame(1, substr_count($taxId[0], 'aria-describedby='), 'An errored field must carry one aria-describedby, not two.');
        $this->assertMatchesRegularExpression('/<input\s+id="registration_number"[^>]*aria-describedby="company-hint"/', $html);
    }

    public function test_an_array_submitted_for_either_field_is_refused_not_crashed(): void
    {
        $response = $this->store(['tax_id' => ['101', '234'], 'registration_number' => ['0123', '4567']]);

        $this->assertNotEmpty($this->errors($response, 'tax_id'));
        $this->assertNotEmpty($this->errors($response, 'registration_number'));
        $this->assertDatabaseCount('customers', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('roles')]
    public function test_every_role_sees_a_companys_pib_and_mb(string $role): void
    {
        $customer = Customer::factory()->create(['tax_id' => self::VALID_TAX_ID, 'registration_number' => '01234567']);

        $html = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->getContent();

        foreach (['tax_id' => self::VALID_TAX_ID, 'registration_number' => '01234567'] as $key => $value) {
            $this->assertMatchesRegularExpression(
                '/<dt[^>]*>\s*'.preg_quote(e(__('customers.show.'.$key)), '/').'\s*<\/dt>\s*<dd class="[^"]*tabular-nums[^"]*">\s*'.$value.'\s*<\/dd>/',
                $html,
            );
        }
    }

    public function test_the_company_rows_sit_between_the_address_and_the_notes(): void
    {
        $customer = Customer::factory()->company()->create();

        $html = $this->actingAsReceptionist()->get(route('customers.show', $customer))->getContent();

        $this->assertSeeInOrderHtml($html, [
            e(__('customers.show.address')),
            e(__('customers.show.tax_id')),
            e(__('customers.show.registration_number')),
            e(__('customers.show.notes')),
        ]);
    }

    public function test_a_private_customers_page_gains_no_rows(): void
    {
        $customer = Customer::factory()->create();

        $html = $this->actingAsReceptionist()->get(route('customers.show', $customer))->assertOk()->getContent();

        $this->assertStringNotContainsString(e(__('customers.show.tax_id')), $html);
        $this->assertStringNotContainsString(e(__('customers.show.registration_number')), $html);
        $this->assertSame(5, substr_count($html, '<dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">'));
    }

    public function test_the_company_factory_state_always_yields_a_valid_pib_and_an_eight_digit_mb(): void
    {
        foreach (Customer::factory()->company()->count(200)->make() as $customer) {
            $this->assertTrue(TaxIdChecksum::isValid($customer->tax_id), "Invalid PIB {$customer->tax_id}");
            $this->assertMatchesRegularExpression('/^\d{8}\z/', $customer->registration_number);
            $this->assertTrue($customer->isCompany());
        }
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertSeeInOrderHtml(string $html, array $needles): void
    {
        $offset = 0;

        foreach ($needles as $needle) {
            $position = strpos($html, $needle, $offset);
            $this->assertNotFalse($position, "'{$needle}' missing or out of order.");
            $offset = $position + strlen($needle);
        }
    }
}
