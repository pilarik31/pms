<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    protected array $providers = [
        'github', //'google'
    ];

    public function index(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required',
            'password' => 'required',
        ],[
            'email.required' => __('validation.email.empty'),
            'password.required' => __('validation.password'),
        ]);

        if (Auth::attempt($credentials)) {
            return redirect()->intended()->with('success', __('auth.success'));
        } else {
            return back()->with('error', __('auth.failed'));
        }
    }

    /**
     * Handle an authentication attepmt from OAuth2.
     */
    protected function loginOAuth(SocialiteUser $providerUser, string $driver): RedirectResponse
    {
        $user = User::where('email', $providerUser->getEmail())->first();

        if ($user) {
            $user->update([
                'provider' => $driver,
                'provider_id' => $providerUser->getId(),
                // @phpstan-ignore-next-line
                'access_token' => $providerUser->token
            ]);

            Auth::login($user, true);
            return $this->sendSuccessResponse();
        }
        return $this->sendFailedResponse(__('auth.oauth.user-not-exist'));
    }

    /**
     * Attempts redirect to specified OAuth2 provider.
     */
    public function redirectToProvider(string $driver): RedirectResponse
    {
        if (!$this->isProviderAllowed($driver)) {
            /** @var string */
            $msg = __('auth.oauth.not-supported', ['provider' => $driver]);
            return $this->sendFailedResponse(
                ucfirst(
                    $msg
                )
            );
        }

        try {
            return Socialite::driver($driver)->redirect();
        } catch (Exception $e) {
            return $this->sendFailedResponse($e->getMessage());
        }
    }

    /**
     * Handles callback from OAuth2 provider.
     */
    public function handleProviderCallback(string $driver): RedirectResponse
    {
        try {
            $user = Socialite::driver($driver)->user();
        } catch (Exception $e) {
            return $this->sendFailedResponse($e->getMessage());
        }

        return empty($user->getEmail())
            ? $this->sendFailedResponse(__('auth.oauth.returned-no-email', ['provider' => $driver]))
            : $this->loginOAuth($user, $driver);
    }

    /**
     * Handle a logout.
     */
    public function logout(): RedirectResponse
    {
        Auth::logout();
        return redirect()->route('login');
    }

    protected function sendSuccessResponse(): RedirectResponse
    {
        return redirect()->intended();
    }

    protected function sendFailedResponse(string|array|null $msg = null): RedirectResponse
    {
        return redirect()->route('login')->with(
            'error',
            $msg ?: __('auth.oauth.failed')
        );
    }

    /**
     * Whether the provider is supported in the app.
     */
    private function isProviderAllowed(string $driver): bool
    {
        return in_array($driver, $this->providers, true) && config()->has("services.{$driver}");
    }
}
