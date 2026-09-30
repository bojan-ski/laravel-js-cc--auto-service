<span>{{ $state }}</span>

@if ($photos)
    <span aria-hidden="true">&middot;</span>
    <span class="tabular-nums">{{ $photos }}</span>
@endif

@if ($linkUrl)
    <span aria-hidden="true">&middot;</span>
    <a href="{{ $linkUrl }}" class="font-medium underline underline-offset-2 hover:text-brand-dark">{{ $linkLabel }}</a>
@endif
