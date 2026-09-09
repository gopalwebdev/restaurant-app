{{--
    Panels are back-office tools and are designed for a laptop or larger.

    Rather than let a phone render a table nobody laid out for it, the panel
    says so and stops. This is not a second layout — building one is exactly
    what .ai/rules/filament.md rules out — it is a door.

    CSS only, and inline: a panel is served Filament's own compiled stylesheet,
    which carries its fi- classes and no general utilities, and a media query
    needs no JavaScript to be correct on first paint.
--}}
<style>
    #panel-needs-a-bigger-screen {
        display: none;
    }

    @media (max-width: 1023px) {
        #panel-needs-a-bigger-screen {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 2rem;
            text-align: center;
            background: #ffffff;
            color: #0f172a;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }

        @media (prefers-color-scheme: dark) {
            #panel-needs-a-bigger-screen {
                background: #0f172a;
                color: #e2e8f0;
            }
        }
    }
</style>

<div id="panel-needs-a-bigger-screen" role="alert">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
         stroke-width="1.5" stroke="currentColor" style="width: 2.5rem; height: 2.5rem; opacity: 0.5;">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
    </svg>

    <p style="font-size: 1.05rem; font-weight: 600; margin: 0;">
        Open this on a laptop
    </p>

    <p style="font-size: 0.875rem; margin: 0; max-width: 26rem; opacity: 0.75;">
        This panel is a back-office tool and needs a wider screen. If you are
        floor staff, the staff app is built for your phone.
    </p>
</div>
