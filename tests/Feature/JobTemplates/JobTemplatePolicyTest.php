<?php

namespace Tests\Feature\JobTemplates;

use App\Models\JobTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobTemplatePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: array<string, bool>}>
     */
    public static function abilityProvider(): array
    {
        return [
            'mechanic' => ['mechanic', [
                'viewAny' => false, 'viewDeleted' => false, 'create' => false, 'update' => false,
                'delete' => false, 'deleteTrashed' => false, 'restore' => false, 'restoreActive' => false,
            ]],
            'receptionist' => ['receptionist', [
                'viewAny' => true, 'viewDeleted' => false, 'create' => true, 'update' => true,
                'delete' => false, 'deleteTrashed' => false, 'restore' => false, 'restoreActive' => false,
            ]],
            'admin' => ['admin', [
                'viewAny' => true, 'viewDeleted' => true, 'create' => true, 'update' => true,
                'delete' => true, 'deleteTrashed' => false, 'restore' => true, 'restoreActive' => false,
            ]],
        ];
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('abilityProvider')]
    public function test_each_ability_per_role(string $role, array $expected): void
    {
        $gate = Gate::forUser(User::factory()->{$role}()->create());
        $active = JobTemplate::factory()->create();
        $trashed = JobTemplate::factory()->trashed()->create();

        $this->assertSame($expected, [
            'viewAny' => $gate->allows('viewAny', JobTemplate::class),
            'viewDeleted' => $gate->allows('viewDeleted', JobTemplate::class),
            'create' => $gate->allows('create', JobTemplate::class),
            'update' => $gate->allows('update', $active),
            'delete' => $gate->allows('delete', $active),
            'deleteTrashed' => $gate->allows('delete', $trashed),
            'restore' => $gate->allows('restore', $trashed),
            'restoreActive' => $gate->allows('restore', $active),
        ]);
    }
}
