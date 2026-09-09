<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="{{ $theme['primary_color'] }}">
        <meta name="robots" content="noindex">

        {{-- Staff install this and keep it, so it is a real PWA: a manifest to
             be installable, and a service worker for the app shell. The guest
             app deliberately has neither — see .ai/rules/js.md. --}}
        <link rel="manifest" href="{{ route('staff.manifest', ['restaurant' => $tenantSlug]) }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ $theme['name'] }}">

        @include('partials.theme')

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/staff.tsx', "resources/js/pages/staff/{$page['component']}.tsx"])

        <x-inertia::head>
            <title>{{ $theme['name'] }} · Staff</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker
                        .register(
                            @json(route('staff.service-worker', ['restaurant' => $tenantSlug])),
                            { scope: @json(route('staff.home', ['restaurant' => $tenantSlug], absolute: false)) },
                        )
                        .catch(function () {
                            // An app that cannot register a worker still works;
                            // it just cannot be installed. Never block on it.
                        });
                });
            }
        </script>
    </body>
</html>
