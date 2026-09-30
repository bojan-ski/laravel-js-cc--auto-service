<x-layouts.app :title="__('templates.create.title')">
    <h1 class="text-2xl font-bold">{{ __('templates.create.title') }}</h1>

    <x-job-templates.form
        :action="route('job-templates.store')"
        :submit="__('templates.create.submit')"
    />
</x-layouts.app>
