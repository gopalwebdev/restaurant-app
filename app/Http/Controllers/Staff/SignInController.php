<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Otp\SendOneTimePassword;
use App\Actions\Otp\ThrottleOneTimePasswordRequests;
use App\Actions\Otp\VerifyOneTimePassword;
use App\Actions\Staff\AuthenticateStaffMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RequestSignInCodeRequest;
use App\Http\Requests\Staff\SignInRequest;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Passwordless sign-in for the staff app.
 *
 * The same two steps as the panels, and the same actions behind them: an
 * address is entered, a code is emailed, and the code signs them in. The
 * difference is who may pass — this checks the roster of the restaurant whose
 * subdomain is being served, so a code issued for one restaurant's staff app
 * cannot open another's.
 */
class SignInController extends Controller
{
    public function __construct(
        private readonly AuthenticateStaffMember $staff,
    ) {}

    /**
     * Step one and two share a page; the code field appears once one is sent.
     */
    public function create(Restaurant $restaurant): Response
    {
        abort_unless($restaurant->is_active, 404);

        return Inertia::render('login', [
            'codeLength' => (int) config('otp.length'),
            'expiresInMinutes' => (int) config('otp.ttl'),
        ]);
    }

    /**
     * Issue a code for an address that may work this floor.
     */
    public function storeCode(RequestSignInCodeRequest $request, Restaurant $restaurant): RedirectResponse
    {
        abort_unless($restaurant->is_active, 404);

        $email = (string) $request->validated('email');

        // As on the panels, this says when an address has no account here. The
        // trade is deliberate and documented in .ai/rules/otp.md: these are
        // small rosters, and "you are not on this restaurant's staff" beats
        // silence for someone standing in a kitchen waiting to start a shift.
        $user = $this->staff->findEligible($restaurant, $email);

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'email' => 'There is no staff account for that email address at this restaurant.',
            ]);
        }

        $outcome = app(ThrottleOneTimePasswordRequests::class)($email);

        if (! $outcome->mayIssueCode()) {
            throw ValidationException::withMessages(['email' => $outcome->message()]);
        }

        app(SendOneTimePassword::class)($user);

        return back()->with('status', $outcome->message());
    }

    /**
     * Check the code and start the session.
     */
    public function store(SignInRequest $request, Restaurant $restaurant): RedirectResponse
    {
        abort_unless($restaurant->is_active, 404);

        $user = $this->staff->findEligible($restaurant, (string) $request->validated('email'));

        // Every failure past this point reads as a bad code, so a wrong address
        // and a wrong code are indistinguishable once a code has been asked for.
        if (! $user instanceof User) {
            throw ValidationException::withMessages(['code' => 'That code is not correct.']);
        }

        $result = app(VerifyOneTimePassword::class)($user, (string) $request->validated('code'));

        if (! $result->isVerified()) {
            throw ValidationException::withMessages(['code' => $result->message()]);
        }

        // Receiving the code proves the address is theirs, which is the same
        // thing a verification email would have established.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        auth()->login($user, remember: true);

        $request->session()->regenerate();

        return to_route('staff.home', ['restaurant' => $restaurant->slug]);
    }

    /**
     * End the session.
     */
    public function destroy(Request $request, Restaurant $restaurant): RedirectResponse
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('staff.login', ['restaurant' => $restaurant->slug]);
    }
}
