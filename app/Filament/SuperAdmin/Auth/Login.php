<?php

namespace App\Filament\SuperAdmin\Auth;

use App\Enums\AdminPanel;
use App\Filament\Auth\OtpLogin;

/**
 * Sign-in for the product team panel, served from the root domain.
 */
class Login extends OtpLogin
{
    protected function panel(): AdminPanel
    {
        return AdminPanel::SuperAdmin;
    }
}
