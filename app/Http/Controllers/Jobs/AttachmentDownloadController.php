<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Models\JobAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentDownloadController extends Controller
{
    public function show(JobAttachment $attachment, string $variant = 'original'): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        $path = $attachment->pathFor($variant);

        abort_if($path === null, 404);

        $disk = Storage::disk(config('attachments.disk'));

        return $attachment->isPhoto()
            ? $disk->response($path)
            : $disk->download($path, $attachment->original_filename);
    }
}
