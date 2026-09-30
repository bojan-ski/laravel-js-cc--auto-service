@props(['job', 'templateOptions', 'userOptions'])

@php
    $choosesMechanic = $userOptions !== [];
    $hintId = $errors->has('user_id') ? null : 'user_id-hint';
@endphp

@if ($templateOptions === [])
    <div id="line-item-form" class="mt-4 border border-border bg-surface-muted p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-text-soft">{{ __('templates.apply.title') }}</h3>

            {{ $switch ?? '' }}
        </div>

        <p class="mt-2 text-sm text-text-soft">
            {{ __('templates.apply.none') }}

            @can('viewAny', App\Models\JobTemplate::class)
                <a href="{{ route('job-templates.index') }}" class="font-medium underline underline-offset-2 hover:text-brand-dark">{{ __('templates.apply.manage') }}</a>
            @endcan
        </p>
    </div>
@else
    <form id="line-item-form" method="POST" action="{{ route('jobs.templates.apply', $job) }}" class="mt-4 border border-border bg-surface-muted p-3">
        @csrf

        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-text-soft">{{ __('templates.apply.title') }}</h3>

            {{ $switch ?? '' }}
        </div>

        <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_14rem_auto]">
            <x-forms.select
                class="lg:h-10.5"
                name="job_template_id"
                :label="__('templates.apply.template')"
                :options="$templateOptions"
                :placeholder="__('templates.apply.template_placeholder')"
                required
            />

            @if ($choosesMechanic)
                <div>
                    <x-forms.select
                        class="lg:h-10.5"
                        name="user_id"
                        :label="__('templates.apply.mechanic')"
                        :options="$userOptions"
                        :placeholder="__('templates.apply.mechanic_placeholder')"
                        :aria-describedby="$hintId"
                    />

                    <p id="user_id-hint" class="mt-1 text-sm text-text-soft">{{ __('templates.apply.mechanic_hint') }}</p>
                </div>
            @endif

            <div class="lg:pt-6">
                <x-ui.button class="lg:h-10.5">{{ __('templates.apply.submit') }}</x-ui.button>
            </div>
        </div>

        @unless ($choosesMechanic)
            <p class="mt-2 text-sm text-text-soft">{{ __('templates.apply.self_hint') }}</p>
        @endunless
    </form>
@endif
