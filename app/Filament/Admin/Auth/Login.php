<?php

namespace App\Filament\Admin\Auth;

use App\Enums\AdminPanel;
use App\Filament\Auth\OtpLogin;

/**
 * Sign-in for a single restaurant's panel, served from its subdomain.
 */
class Login extends OtpLogin
{
    protected function panel(): AdminPanel
    {
        return AdminPanel::Admin;
    }
}
