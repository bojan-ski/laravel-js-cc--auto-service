<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerRemindersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function receptionist(): User
    {
        return User::factory()->receptionist()->create();
    }

    private function checkbox(string $html): string
    {
        preg_match('/<input\b[^>]*type="checkbox"[^>]*name="receives_reminders"[^>]*>|<input\b[^>]*name="receives_reminders"[^>]*type="checkbox"[^>]*>/s', $html, $match);

        $this->assertNotEmpty($match, 'The form renders no reminders checkbox.');

        return $match[0];
    }

    public function test_a_customer_can_be_reminded_only_when_active_with_an_email_and_opted_in(): void
    {
        $this->assertTrue(Customer::factory()->create(['email' => 'a@example.test'])->canBeReminded());
        $this->assertFalse(Customer::factory()->create(['email' => null])->canBeReminded());
        $this->assertFalse(Customer::factory()->create(['email' => ''])->canBeReminded());
        $this->assertFalse(Customer::factory()->create(['receives_reminders' => false])->canBeReminded());

        $trashed = Customer::factory()->create();
        $trashed->delete();

        $this->assertFalse($trashed->canBeReminded());
    }

    public function test_a_new_customer_receives_reminders_by_default(): void
    {
        $this->assertTrue((new Customer)->receives_reminders);
        $this->assertTrue(Customer::factory()->create()->fresh()->receives_reminders);
    }

    public function test_the_create_form_renders_the_box_checked_after_a_hidden_zero(): void
    {
        $html = $this->actingAs($this->receptionist())->get(route('customers.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\bchecked\b/', $this->checkbox($html));
        $this->assertMatchesRegularExpression('/<input type="hidden" name="receives_reminders" value="0">\s*<div[^>]*>\s*<input\b[^>]*name="receives_reminders"/s', $html);
    }

    public function test_a_create_submitted_with_the_box_untouched_stores_true(): void
    {
        $this->actingAs($this->receptionist())
            ->post(route('customers.store'), [
                'name' => 'Ana Jovic',
                'phone' => '060 111 222',
                'receives_reminders' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue(Customer::query()->where('name', 'Ana Jovic')->sole()->receives_reminders);
    }

    public function test_a_create_without_the_field_takes_the_default(): void
    {
        $this->actingAs($this->receptionist())
            ->post(route('customers.store'), ['name' => 'Ana Jovic', 'phone' => '060 111 222'])
            ->assertRedirect();

        $this->assertTrue(Customer::query()->where('name', 'Ana Jovic')->sole()->receives_reminders);
    }

    public function test_unchecking_the_box_saves_false_and_the_edit_form_shows_it_unchecked(): void
    {
        $customer = Customer::factory()->create();
        $actor = $this->receptionist();

        $this->actingAs($actor)
            ->put(route('customers.update', $customer), [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'receives_reminders' => '0',
            ])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertFalse($customer->fresh()->receives_reminders);

        $html = $this->actingAs($actor)->get(route('customers.edit', $customer))->getContent();

        $this->assertDoesNotMatchRegularExpression('/\bchecked\b/', $this->checkbox($html));
    }

    public function test_the_edit_form_renders_the_box_checked_for_an_opted_in_customer(): void
    {
        $customer = Customer::factory()->create(['receives_reminders' => true]);

        $html = $this->actingAs($this->receptionist())->get(route('customers.edit', $customer))->getContent();

        $this->assertMatchesRegularExpression('/\bchecked\b/', $this->checkbox($html));
    }

    /**
     * Submits the edit form as a browser would — the hidden 0 always, the
     * checkbox's 1 only while it is ticked — with one unrelated change, so an
     * edit form that forgot the stored setting would opt the customer out.
     */
    public function test_an_unrelated_edit_through_the_rendered_form_keeps_the_customer_opted_in(): void
    {
        $customer = Customer::factory()->create(['receives_reminders' => true]);
        $actor = $this->receptionist();

        $html = $this->actingAs($actor)->get(route('customers.edit', $customer))->getContent();

        preg_match_all('/<input\b[^>]*>/s', $html, $inputs);

        $payload = [];

        foreach ($inputs[0] as $input) {
            if (preg_match('/\bname="([^"]+)"/', $input, $name) !== 1 || preg_match('/\bvalue="([^"]*)"/', $input, $value) !== 1) {
                continue;
            }

            if (str_contains($input, 'type="checkbox"') && preg_match('/\bchecked\b/', $input) !== 1) {
                continue;
            }

            $payload[$name[1]] = html_entity_decode($value[1], ENT_QUOTES);
        }

        $payload['name'] = 'Renamed Customer';

        $this->actingAs($actor)->put(route('customers.update', $customer), $payload)->assertRedirect(route('customers.show', $customer));

        $this->assertSame('Renamed Customer', $customer->fresh()->name);
        $this->assertTrue($customer->fresh()->receives_reminders);
    }

    public function test_an_update_without_the_field_leaves_it_unchanged(): void
    {
        $customer = Customer::factory()->create(['receives_reminders' => false]);

        $this->actingAs($this->receptionist())
            ->put(route('customers.update', $customer), ['name' => 'Renamed', 'phone' => $customer->phone])
            ->assertRedirect();

        $this->assertFalse($customer->fresh()->receives_reminders);
    }

    /**
     * Without the hidden 0, an unchecked box submits nothing, so `old()` falls
     * back to the default and silently re-ticks the box the user cleared. The
     * name field's repopulation is the positive control: it proves old input
     * reached this render at all.
     */
    public function test_a_failed_submit_with_the_box_unchecked_re_renders_it_unchecked(): void
    {
        $actor = $this->receptionist();

        $this->actingAs($actor)
            ->from(route('customers.create'))
            ->post(route('customers.store'), [
                'name' => 'Kept Name',
                'phone' => '',
                'receives_reminders' => '0',
            ])
            ->assertRedirect(route('customers.create'));

        $html = $this->actingAs($actor)->get(route('customers.create'))->getContent();

        $this->assertStringContainsString('value="Kept Name"', $html);
        $this->assertDoesNotMatchRegularExpression('/\bchecked\b/', $this->checkbox($html));
    }

    public function test_the_customer_page_shows_the_setting(): void
    {
        $actor = $this->receptionist();

        $this->actingAs($actor)
            ->get(route('customers.show', Customer::factory()->create()))
            ->assertSeeInOrder([__('customers.show.receives_reminders'), __('customers.show.reminders_on'), __('customers.show.notes')]);

        $this->actingAs($actor)
            ->get(route('customers.show', Customer::factory()->create(['receives_reminders' => false])))
            ->assertSeeInOrder([__('customers.show.receives_reminders'), __('customers.show.reminders_off'), __('customers.show.notes')]);
    }

    public function test_a_customer_created_from_the_walk_in_picker_receives_reminders(): void
    {
        $this->actingAs($this->receptionist())
            ->post(route('jobs.store'), [
                'new_customer_name' => 'Picker Customer',
                'new_customer_phone' => '061 999 000',
                'new_vehicle_plate' => 'BG-PICK-01',
                'new_vehicle_make' => 'Fiat',
                'new_vehicle_model' => 'Punto',
                'complaint' => 'Noise',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Customer::query()->where('name', 'Picker Customer')->sole()->receives_reminders);
    }
}
