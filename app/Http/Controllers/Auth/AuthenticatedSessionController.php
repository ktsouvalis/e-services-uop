<?php

namespace App\Http\Controllers\Auth;

use Illuminate\View\View;
use LdapRecord\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Models\OpenLDAP\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Context;
use App\Http\Requests\Auth\LoginRequest;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        // $request->authenticate();
        // dd($request->all());
        $username = $request->input('username');
        $password = $request->input('password');

        // Connect to the LDAP server
        $ldap = Container::getDefaultConnection();
        $ldap->connect();
               
        // Search for the user
        $user = User::where('uid', '=', $username)->first();

        if (!$user) {
            return redirect()->back()->with('error', 'Invalid credentials');
        }
        
        // Attempt to bind with the user's credentials
        $isAuthenticated = $ldap->auth()->attempt($user, $password);
        if($isAuthenticated){
            auth()->login(\App\Models\User::where('username', $username)->firstOrFail());
            session()->regenerate();
        }
        else{
            return redirect()->back()->with('error', 'Invalid credentials');
        }
        

        Log::info('User logged in.');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
