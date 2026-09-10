{{--
    What a visitor looks at while the app's JavaScript is on its way.

    CSS only, and inline, for the same reason the theme script above it is: this
    has to be right on the first paint, and anything that needed the bundle
    would arrive at the same moment as the app it is meant to be covering.

    `#app:not(:empty)` is the whole mechanism. Inertia renders into that div, so
    the browser stops matching this the instant the first page is on screen —
    there is nothing to unmount, nothing to time out, and nothing to go wrong if
    the bundle is slow. Include it after <x-inertia::app />.

    The combinator is `~` and not `+`: the style block below is itself a sibling
    sitting between the two, so an adjacent-sibling rule matches nothing and the
    spinner stays on screen for ever over a page that has actually loaded.

    Every navigation after this one is covered by Inertia's own progress bar,
    configured in each app's entry.
--}}
<style>
    #boot-loader {
        position: fixed;
        inset: 0;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
    }

    :root.dark #boot-loader {
        background: #0a0a0a;
    }

    #app:not(:empty) ~ #boot-loader {
        display: none;
    }

    #boot-loader > span {
        width: 2rem;
        height: 2rem;
        border-radius: 9999px;
        border: 2px solid rgba(120, 120, 120, 0.25);
        border-top-color: rgba(120, 120, 120, 0.9);
        animation: boot-loader-spin 0.7s linear infinite;
    }

    @media (prefers-reduced-motion: reduce) {
        #boot-loader > span {
            animation-duration: 2.5s;
        }
    }

    @keyframes boot-loader-spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

<div id="boot-loader" role="status" aria-label="Loading">
    <span></span>
</div>
