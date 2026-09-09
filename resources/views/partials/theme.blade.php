{{--
    The restaurant's own colour, injected before anything renders.

    shadcn draws every component from CSS custom properties, so overriding
    --primary here re-skins the whole app without a rebuild. It is inline
    rather than in a stylesheet because it differs per restaurant, and a
    stylesheet would have to be built per tenant to say the same thing.
--}}
<style>
    :root {
        --primary: {{ $theme['primary_color'] }};
        --primary-foreground: #ffffff;
        --ring: {{ $theme['primary_color'] }};
    }
</style>

<script>
    (function () {
        const appearance = @json($theme['appearance']);
        const prefersDark =
            appearance === 'dark' ||
            (appearance === 'system' &&
                window.matchMedia('(prefers-color-scheme: dark)').matches);

        if (prefersDark) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
