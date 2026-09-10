<?php

namespace App\Http\Controllers;

use App\Enums\FilamentPanel;
use App\Models\Restaurant;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;

/**
 * /login, on the root domain and on every restaurant's subdomain.
 *
 * The one address to give anyone who uses a panel, whatever they do there.
 * Filament keeps a panel's sign-in page under the panel's own path
 * (/dashboard/login), so this is a door rather than a page: someone signed out
 * is sent to that sign-in page, and someone already signed in goes straight to
 * /dashboard.
 */
class PanelSignInController extends Controller
{
    /**
     * The product team's way in, on the root domain.
     */
    public function platform(): RedirectResponse
    {
        return $this->enter(Filament::getPanel(FilamentPanel::Platform->value));
    }

    /**
     * A restaurant's way in, for its admins and staff alike, on its subdomain.
     */
    public function restaurant(Restaurant $restaurant): RedirectResponse
    {
        return $this->enter(Filament::getPanel(FilamentPanel::Restaurant->value), ['tenant' => $restaurant]);
    }

    /**
     * @param  array<string, mixed>  $dashboardParameters
     */
    private function enter(Panel $panel, array $dashboardParameters = []): RedirectResponse
    {
        if ($panel->auth()->check()) {
            return redirect()->to(route($panel->generateRouteName('pages.dashboard'), $dashboardParameters));
        }

        return redirect()->to((string) $panel->getLoginUrl());
    }
}
