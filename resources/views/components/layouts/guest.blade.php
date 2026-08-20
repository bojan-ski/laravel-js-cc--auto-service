@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ __('app.document_title', ['page' => $title, 'app' => __('app.name')]) }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-background text-text antialiased">
    <x-partials.flash />

    <div class="flex flex-1 flex-col items-center justify-center px-3 py-8">
        <span class="mb-4 text-sm font-bold uppercase tracking-wider">{{ __('app.name') }}</span>

        <div class="w-full max-w-sm rounded-sm border border-border bg-surface p-6">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
