<header class="relative border-b border-border-strong bg-surface-dark text-text-inverse print:hidden">
    <div class="mx-auto flex w-full max-w-7xl items-center gap-6 px-3 md:px-4 lg:px-6">
        <a
            href="{{ route('dashboard') }}"
            class="rounded-sm py-3 text-sm font-bold uppercase tracking-wider focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
        >
            {{ __('app.name') }}
        </a>

        <nav class="hidden md:flex md:items-center md:gap-1" aria-label="{{ __('app.nav.primary') }}">
            @foreach ($items as $item)
                <x-global.nav-link :item="$item" />
            @endforeach
        </nav>

        <details class="relative ms-auto hidden md:block" data-dropdown>
            <summary
                class="cursor-pointer list-none py-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent [&::-webkit-details-marker]:hidden"
            >
                {{ $user->name }}
                <span class="sr-only">{{ __('app.nav.user_menu') }}</span>
                <span aria-hidden="true">&#9662;</span>
            </summary>

            <div class="absolute inset-e-0 top-full z-10 min-w-48 border border-border-strong bg-surface-dark py-1">
                <p class="px-3 py-1.5 text-xs font-medium uppercase tracking-wider text-text-muted">
                    {{ $user->role->label() }}
                </p>

                @foreach ($adminLinks as $adminLink)
                    <a
                        href="{{ $adminLink['url'] }}"
                        @if ($adminLink['active']) aria-current="page" @endif
                        class="block px-3 py-2 text-sm font-medium text-text-inverse/80 hover:bg-text-inverse/10 hover:text-text-inverse focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    >
                        {{ $adminLink['label'] }}
                    </a>
                @endforeach

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"
                        class="block w-full px-3 py-2 text-start text-sm font-medium text-text-inverse/80 hover:bg-text-inverse/10 hover:text-text-inverse focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    >
                        {{ __('app.nav.sign_out') }}
                    </button>
                </form>
            </div>
        </details>

        <details class="ms-auto md:hidden" data-dropdown>
            <summary
                class="cursor-pointer list-none py-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent [&::-webkit-details-marker]:hidden"
            >
                {{ __('app.nav.toggle') }}
            </summary>

            <div class="absolute inset-x-0 top-full z-10 border-b border-border-strong bg-surface-dark px-3 pb-2">
                <nav aria-label="{{ __('app.nav.primary') }}">
                    @foreach ($items as $item)
                        <x-global.nav-link :item="$item" :block="true" />
                    @endforeach
                </nav>

                <div class="mt-2 border-t border-border-strong pt-2">
                    <p class="px-3 pt-1 text-sm font-medium">{{ $user->name }}</p>

                    <p class="px-3 pb-1 text-xs font-medium uppercase tracking-wider text-text-muted">
                        {{ $user->role->label() }}
                    </p>

                    @foreach ($adminLinks as $adminLink)
                        <a
                            href="{{ $adminLink['url'] }}"
                            @if ($adminLink['active']) aria-current="page" @endif
                            class="block rounded-sm px-3 py-2 text-sm font-medium text-text-inverse/80 hover:bg-text-inverse/10 hover:text-text-inverse focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        >
                            {{ $adminLink['label'] }}
                        </a>
                    @endforeach

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button
                            type="submit"
                            class="block w-full rounded-sm px-3 py-2 text-start text-sm font-medium text-text-inverse/80 hover:bg-text-inverse/10 hover:text-text-inverse focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        >
                            {{ __('app.nav.sign_out') }}
                        </button>
                    </form>
                </div>
            </div>
        </details>
    </div>
</header>
