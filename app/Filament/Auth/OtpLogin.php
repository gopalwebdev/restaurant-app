<?php

namespace App\Filament\Auth;

use App\Actions\Otp\SendOneTimePassword;
use App\Actions\Otp\ThrottleOneTimePasswordRequests;
use App\Actions\Otp\VerifyOneTimePassword;
use App\Enums\AdminPanel;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Passwordless sign-in, shared by every panel.
 *
 * The page runs in two steps inside one Livewire component: an address is
 * entered, a one-time code is issued for it, and the same page then asks for
 * that code. Whether an account exists for the address is never disclosed —
 * step one always reports the same thing, and every failure in step two reads
 * as a bad code.
 *
 * Each panel registers its own subclass so that its pages stay in its own
 * namespace and it can speak for itself on the way in.
 */
abstract class OtpLogin extends BaseLogin
{
    /**
     * Whether the page has moved on to asking for the code.
     */
    #[Locked]
    public bool $hasRequestedCode = false;

    /**
     * The panel this page signs people into.
     */
    abstract protected function panel(): AdminPanel;

    /**
     * Issue a code for the address that was entered and move to step two.
     */
    public function requestCode(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        // Only the address is validated here. Taking the whole form's state
        // would trip over the empty code field when someone on step two asks
        // for a replacement code.
        $this->validateOnly('data.email', [
            'data.email' => ['required', 'string', 'email'],
        ], attributes: ['data.email' => 'email address']);

        $email = (string) ($this->data['email'] ?? '');

        // Nothing is sent to an address that cannot sign in here, and the page
        // says so rather than pretending a code is on its way.
        $user = User::query()->withEmail($email)->first();

        if (! ($user instanceof User) || ! $this->isUserAllowedToAccessPanel($user)) {
            throw ValidationException::withMessages([
                'data.email' => 'There is no account for that email address.',
            ]);
        }

        $outcome = app(ThrottleOneTimePasswordRequests::class)($email);

        if (! $outcome->mayIssueCode()) {
            Notification::make()
                ->title('Hold on')
                ->body($outcome->message())
                ->warning()
                ->send();

            return;
        }

        app(SendOneTimePassword::class)($user);

        $this->hasRequestedCode = true;

        Notification::make()
            ->title('Check your email')
            ->body($outcome->message())
            ->success()
            ->send();
    }

    /**
     * Go back to step one so a different address can be entered.
     */
    public function startOver(): void
    {
        $this->hasRequestedCode = false;

        $this->form->fill(['email' => $this->data['email'] ?? null]);
    }

    /**
     * Check the submitted code and sign the user in.
     */
    public function authenticate(): ?LoginResponse
    {
        if (! $this->hasRequestedCode) {
            $this->requestCode();

            return null;
        }

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        $user = User::query()->withEmail((string) $data['email'])->first();

        if (! ($user instanceof User) || ! $this->isUserAllowedToAccessPanel($user)) {
            $this->throwFailureValidationException();
        }

        $result = app(VerifyOneTimePassword::class)($user, $data['code']);

        if (! $result->isVerified()) {
            throw ValidationException::withMessages([
                'data.code' => $result->message(),
            ]);
        }

        // Receiving the code proves the address belongs to them, which is the
        // same thing a verification email would have established.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Filament::auth()->login($user);

        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getCodeFormComponent(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE),
                $this->getFormContentComponent(),
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler($this->hasRequestedCode ? 'authenticate' : 'requestCode')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->key('form-actions'),
            ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Sign in';
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->hasRequestedCode ? 'Enter your code' : 'Sign in';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! $this->hasRequestedCode) {
            return $this->panel()->signInDescription().' We will email you a code; there is no password.';
        }

        return sprintf('We emailed you a code. It expires in %d minutes.', (int) config('otp.ttl'));
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email address')
            ->email()
            ->required()
            ->autocomplete('username')
            ->autofocus(fn (): bool => ! $this->hasRequestedCode)
            ->readOnly(fn (): bool => $this->hasRequestedCode);
    }

    protected function getCodeFormComponent(): Component
    {
        $length = $this->codeLength();

        return TextInput::make('code')
            ->label(sprintf('%d-digit code', $length))
            ->required()
            ->rule('digits:'.$length)
            ->autocomplete('one-time-code')
            ->autofocus(fn (): bool => $this->hasRequestedCode)
            ->live(debounce: 200)
            ->extraInputAttributes([
                'inputmode' => 'numeric',
                'maxlength' => $length,
                'placeholder' => str_repeat('0', $length),
            ])
            ->visible(fn (): bool => $this->hasRequestedCode);
    }

    /**
     * How many digits a code carries.
     */
    protected function codeLength(): int
    {
        return (int) config('otp.length');
    }

    /**
     * Whether every digit of the code has been typed.
     *
     * The sign-in button only appears once this is true, so the form is never
     * submitted half-filled.
     */
    protected function hasCompleteCode(): bool
    {
        return mb_strlen((string) ($this->data['code'] ?? '')) === $this->codeLength();
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getFormActions(): array
    {
        if (! $this->hasRequestedCode) {
            return [$this->getRequestCodeFormAction()];
        }

        return array_values(array_filter([
            $this->hasCompleteCode() ? $this->getAuthenticateFormAction() : null,
            $this->getResendCodeFormAction(),
            $this->getStartOverFormAction(),
        ]));
    }

    protected function getRequestCodeFormAction(): Action
    {
        return Action::make('requestCode')
            ->label('Email me a code')
            ->submit('requestCode');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label('Sign in')
            ->submit('authenticate');
    }

    protected function getResendCodeFormAction(): Action
    {
        return Action::make('resendCode')
            ->label('Send a new code')
            ->link()
            ->action('requestCode');
    }

    protected function getStartOverFormAction(): Action
    {
        return Action::make('startOver')
            ->label('Use a different email')
            ->link()
            ->action('startOver');
    }

    /**
     * The panel never distinguishes a wrong code from an unusable account.
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.code' => 'That code is not correct.',
        ]);
    }
}
