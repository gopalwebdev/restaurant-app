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

        // Left on the element for React to read back. The server has already
        // decided this — the visitor's own choice if they have made one, the
        // restaurant's setting otherwise — and the theme toggle has to start
        // in the same state the page was painted in rather than guess and
        // then correct itself in front of the guest.
        document.documentElement.dataset.appearance = appearance;
    })();
</script>
