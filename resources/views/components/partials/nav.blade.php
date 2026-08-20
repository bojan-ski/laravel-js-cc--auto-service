<header class="relative border-b border-border-strong bg-surface-dark text-text-inverse">
    <div class="mx-auto flex w-full max-w-7xl items-center gap-6 px-3 md:px-4 lg:px-6">
        <span class="py-3 text-sm font-bold uppercase tracking-wider">{{ __('app.name') }}</span>

        <nav class="hidden md:flex md:items-center md:gap-1" aria-label="{{ __('app.nav.primary') }}">
            <a
                href="{{ route('dashboard') }}"
                class="rounded-sm px-3 py-2 text-sm font-medium text-text-inverse/80 hover:bg-text-inverse/10 hover:text-text-inverse focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            >
                {{ __('app.nav.dashboard') }}
            </a>
        </nav>

        <span class="ms-auto hidden py-3 text-sm font-medium text-text-inverse/80 md:inline-block">
            {{ __('app.nav.account') }}
        </span>

        <details class="ms-auto md:hidden">
            <summary
                class="cursor-pointer list-none py-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent [&::-webkit-details-marker]:hidden"
            >
                {{ __('app.nav.toggle') }}
            </summary>

            <nav
                class="absolute inset-x-0 top-full z-10 border-b border-border-strong bg-surface-dark px-3 pb-2"
                aria-label="{{ __('app.nav.primary') }}"
            >
                <a
                    href="{{ route('dashboard') }}"
                    class="block rounded-sm px-3 py-2 text-sm font-medium text-text-inverse/80 hover:bg-text-inverse/10 hover:text-text-inverse focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                >
                    {{ __('app.nav.dashboard') }}
                </a>
            </nav>
        </details>
    </div>
</header>
