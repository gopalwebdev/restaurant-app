<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * What makes the staff app installable.
 *
 * Both are served per restaurant rather than as static files, because the name
 * on the home screen and the splash colour are the restaurant's own — a phone
 * with two restaurants installed should show two apps, not one twice.
 *
 * The guest app has neither of these on purpose: guests arrive by QR and leave,
 * and an install prompt at a table is noise. See .ai/rules/js.md.
 */
class ProgressiveWebAppController extends Controller
{
    public function manifest(Restaurant $restaurant): JsonResponse
    {
        abort_unless($restaurant->is_active, 404);

        $settings = $restaurant->settings;
        $start = route('staff.home', ['restaurant' => $restaurant->slug], absolute: false);

        return response()->json([
            'name' => $restaurant->name.' Staff',
            'short_name' => $restaurant->name,
            'description' => 'Take and work orders at '.$restaurant->name.'.',
            'start_url' => $start,
            'scope' => $start,
            // Standalone is the point of installing: no browser chrome, and a
            // tap target the size of the whole screen.
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            'theme_color' => $settings->theme_primary_color ?? '#E11D48',
            'icons' => [
                [
                    'src' => '/apple-touch-icon.png',
                    'sizes' => '180x180',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
            ],
        ]);
    }

    /**
     * A deliberately thin service worker.
     *
     * It exists to make the app installable and to survive a dropped signal
     * mid-shift, not to work offline: there is no offline requirement, and
     * Inertia needs the server for every page anyway. So it caches nothing it
     * would have to invalidate, and never serves a stale page — it passes
     * everything through and only answers from cache when the network fails
     * outright.
     */
    public function serviceWorker(Restaurant $restaurant): Response
    {
        $shell = route('staff.login', ['restaurant' => $restaurant->slug], absolute: false);
        $cache = 'staff-'.$restaurant->slug.'-v1';

        $javascript = <<<JS
        const CACHE = {$this->js($cache)};
        const SHELL = {$this->js($shell)};

        self.addEventListener('install', (event) => {
            event.waitUntil(
                caches.open(CACHE).then((cache) => cache.addAll([SHELL])),
            );
            self.skipWaiting();
        });

        self.addEventListener('activate', (event) => {
            event.waitUntil(
                caches.keys().then((keys) =>
                    Promise.all(
                        keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)),
                    ),
                ),
            );
            self.clients.claim();
        });

        // Network first, always. A cached answer is a last resort for a phone
        // that has walked out of range, never the normal path.
        self.addEventListener('fetch', (event) => {
            if (event.request.method !== 'GET') {
                return;
            }

            event.respondWith(
                fetch(event.request).catch(() =>
                    caches.match(event.request).then((hit) => hit ?? caches.match(SHELL)),
                ),
            );
        });
        JS;

        return response($javascript, 200, [
            'Content-Type' => 'application/javascript',
            // Scope is the whole /staff path, which a worker served from a
            // deeper URL is not allowed to claim without being told.
            'Service-Worker-Allowed' => $shell,
        ]);
    }

    /**
     * Encode a PHP value as a JavaScript literal.
     */
    private function js(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
