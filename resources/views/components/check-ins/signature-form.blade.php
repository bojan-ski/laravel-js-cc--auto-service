@props(['job'])

@php
    $padDescribedBy = trim('signature-pad-hint'.($errors->has('signature') ? ' signature-error' : ''));
@endphp

<form method="POST" action="{{ route('jobs.check-in.sign', $job) }}" data-signature-form class="mt-3 max-w-2xl space-y-4">
    @csrf
    @method('PATCH')

    <x-forms.field
        name="signed_by_name"
        :label="__('check_ins.fields.signed_by_name')"
        :value="$job->customer->name"
        maxlength="255"
        autocomplete="off"
        required
    />

    <input type="hidden" name="signature" value="" data-signature-input>

    <div data-signature-drawn hidden class="space-y-2">
        <div class="flex flex-wrap items-end gap-2">
            <canvas
                data-signature-canvas
                role="img"
                aria-label="{{ __('check_ins.sign.pad_label') }}"
                aria-describedby="{{ $padDescribedBy }}"
                class="block aspect-3/1 min-h-11 w-full max-w-xl touch-none border border-border-strong"
            ></canvas>

            <x-ui.button type="button" variant="secondary" data-signature-clear>
                {{ __('check_ins.sign.clear') }}
            </x-ui.button>
        </div>

        <p id="signature-pad-hint" class="text-sm text-text-muted">{{ __('check_ins.sign.pad_hint') }}</p>

        <p data-signature-empty hidden role="alert" class="text-sm text-status-cancelled">{{ __('check_ins.sign.errors.empty') }}</p>

        @error('signature')
            <p id="signature-error" class="text-sm text-status-cancelled">{{ $message }}</p>
        @enderror

        <x-ui.button type="submit" name="signature_method" value="drawn" data-signature-drawn hidden disabled>
            {{ __('check_ins.sign.submit') }}
        </x-ui.button>
    </div>

    <div class="space-y-2 border-t border-border pt-3">
        <p id="signature-paper-hint" class="text-sm text-text-soft">{{ __('check_ins.sign.paper_hint') }}</p>

        <x-ui.button type="submit" variant="secondary" name="signature_method" value="paper" aria-describedby="signature-paper-hint">
            {{ __('check_ins.sign.paper_submit') }}
        </x-ui.button>
    </div>
</form>
