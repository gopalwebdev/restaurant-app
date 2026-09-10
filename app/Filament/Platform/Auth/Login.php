<?php

namespace App\Filament\Platform\Auth;

use App\Enums\FilamentPanel;
use App\Filament\Auth\OtpLogin;

/**
 * Sign-in for the product team panel, served from the root domain.
 */
class Login extends OtpLogin
{
    protected function panel(): FilamentPanel
    {
        return FilamentPanel::Platform;
    }
}
