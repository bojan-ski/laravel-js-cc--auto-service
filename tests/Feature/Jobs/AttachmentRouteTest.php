<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\User;
use App\Policies\JobAttachmentPolicy;
use App\Policies\JobPolicy;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class AttachmentRouteTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

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
    public function test_every_role_may_upload(string $role): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realImageUpload('photo.jpg'),
            ])
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame(1, JobAttachment::count());
    }

    #[DataProvider('roles')]
    public function test_every_role_may_fetch_every_variant_of_a_photo(string $role): void
    {
        $attachment = $this->storedPhoto();

        foreach (['thumb', 'full', 'original'] as $variant) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('attachments.show', ['attachment' => $attachment, 'variant' => $variant]))
                ->assertOk();
        }
    }

    public function test_the_default_variant_is_the_original(): void
    {
        $attachment = $this->storedPhoto();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('attachments.show', $attachment))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_each_photo_variant_returns_its_own_bytes(): void
    {
        $attachment = $this->storedPhoto();
        $user = User::factory()->mechanic()->create();

        $thumb = $this->actingAs($user)
            ->get(route('attachments.show', ['attachment' => $attachment, 'variant' => 'thumb']))
            ->assertHeader('content-type', 'image/webp')
            ->streamedContent();

        $full = $this->actingAs($user)
            ->get(route('attachments.show', ['attachment' => $attachment, 'variant' => 'full']))
            ->assertHeader('content-type', 'image/webp')
            ->streamedContent();

        $this->assertNotSame($thumb, $full);
        $this->assertSame(Storage::disk('local')->get($attachment->variant_thumb_path), $thumb);
        $this->assertSame(Storage::disk('local')->get($attachment->variant_full_path), $full);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function variantsADocumentDoesNotHave(): array
    {
        return [
            'thumb' => ['thumb'],
            'full' => ['full'],
        ];
    }

    #[DataProvider('variantsADocumentDoesNotHave')]
    public function test_requesting_a_variant_a_document_does_not_have_returns_404(string $variant): void
    {
        $attachment = $this->storedDocument();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('attachments.show', ['attachment' => $attachment, 'variant' => $variant]))
            ->assertNotFound();
    }

    public function test_a_document_is_served_as_a_download_named_by_its_original_filename(): void
    {
        $attachment = $this->storedDocument();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('attachments.show', $attachment))
            ->assertOk()
            ->assertDownload($attachment->original_filename);
    }

    public function test_an_unrecognised_variant_returns_404(): void
    {
        $attachment = $this->storedPhoto();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/attachments/'.$attachment->getKey().'/nonsense')
            ->assertNotFound();
    }

    /**
     * The status alone cannot tell the two 404s apart: without the whereIn
     * constraint the request still 404s, just from the controller's null
     * pathFor() check instead. Matching at the router proves it never gets
     * that far: only the 404 fallback route matches the URL.
     */
    public function test_an_unrecognised_variant_is_refused_by_the_route_itself(): void
    {
        $matched = Route::getRoutes()->match(Request::create('/attachments/1/nonsense', 'GET'));

        $this->assertTrue($matched->isFallback, 'The URL matched '.$matched->getName().' instead of the 404 fallback.');
    }

    public function test_each_accepted_variant_does_match_the_route(): void
    {
        foreach (['thumb', 'full', 'original'] as $variant) {
            $matched = Route::getRoutes()->match(Request::create("/attachments/1/{$variant}", 'GET'));

            $this->assertSame('attachments.show', $matched->getName());
        }
    }

    public function test_the_uploader_may_delete_their_own_attachment(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $attachment = $this->storedPhoto($mechanic);

        $this->actingAs($mechanic)
            ->delete(route('attachments.destroy', $attachment))
            ->assertRedirect(route('jobs.show', $attachment->job_id));

        $this->assertSame(0, JobAttachment::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function frontOfficeRoles(): array
    {
        return [
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('frontOfficeRoles')]
    public function test_front_office_may_delete_an_attachment_uploaded_by_someone_else(string $role): void
    {
        $attachment = $this->storedPhoto(User::factory()->mechanic()->create());

        $this->actingAs(User::factory()->{$role}()->create())
            ->delete(route('attachments.destroy', $attachment))
            ->assertRedirect(route('jobs.show', $attachment->job_id));

        $this->assertSame(0, JobAttachment::count());
    }

    public function test_a_mechanic_may_not_delete_a_colleagues_attachment(): void
    {
        $attachment = $this->storedPhoto(User::factory()->mechanic()->create());

        $this->actingAs(User::factory()->mechanic()->create())
            ->delete(route('attachments.destroy', $attachment))
            ->assertForbidden();

        $this->assertSame(1, JobAttachment::count());
        Storage::disk('local')->assertExists($attachment->path);
    }

    /**
     * JobPolicy::upload and JobAttachmentPolicy::view both return true for every
     * authenticated user, and the routes already sit behind `auth` — so deleting
     * their Gate::authorize call changes nothing observable. These two tests swap
     * the container's policy for a denying one, which is the only way to prove the
     * controller actually asks.
     */
    public function test_the_upload_route_consults_the_job_policy(): void
    {
        Storage::fake('local');

        $this->instance(JobPolicy::class, new class extends JobPolicy
        {
            public function upload(User $user, Job $job): bool
            {
                return false;
            }
        });

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realImageUpload('photo.jpg'),
            ])
            ->assertForbidden();

        $this->assertSame(0, JobAttachment::count());
    }

    public function test_the_download_route_consults_the_attachment_policy(): void
    {
        $attachment = $this->storedPhoto();

        $this->instance(JobAttachmentPolicy::class, new class extends JobAttachmentPolicy
        {
            public function view(User $user, JobAttachment $attachment): bool
            {
                return false;
            }
        });

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('attachments.show', $attachment))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_from_every_attachment_route(): void
    {
        $attachment = $this->storedPhoto();

        $this->get(route('attachments.show', $attachment))->assertRedirect(route('login'));
        $this->delete(route('attachments.destroy', $attachment))->assertRedirect(route('login'));
        $this->post(route('jobs.attachments.store', $attachment->job_id))->assertRedirect(route('login'));

        $this->assertSame(1, JobAttachment::count());
    }

    private function storedPhoto(?User $uploader = null): JobAttachment
    {
        Storage::fake('local');

        return app(AttachmentService::class)->upload(
            Job::factory()->inProgress()->create(),
            $this->realImageUpload('photo.jpg', 900, 700),
            $uploader ?? User::factory()->mechanic()->create(),
        );
    }

    private function storedDocument(): JobAttachment
    {
        Storage::fake('local');

        return app(AttachmentService::class)->upload(
            Job::factory()->inProgress()->create(),
            $this->realUpload('inspection.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\ntrailer<</Root 1 0 R>>"),
            User::factory()->mechanic()->create(),
        );
    }
}
