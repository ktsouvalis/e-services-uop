<?php

namespace App\Http\Controllers\Auth;

use Illuminate\View\View;
use LdapRecord\Container;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use LdapRecord\Auth\BindException;
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
        
        $username = $request->input('username');
        $password = $request->input('password');

        // check if user exists in the database
        $app_user = \App\Models\User::where('username', $username)->first();
        // if (!$app_user) {
        //     return redirect()->back()->with('error', 'Invalid credentials');
        // }

        // // Connect to the LDAP server
        // $ldap = Container::getDefaultConnection();
        // try {
        //     $ldap->connect();
        // } 
        // catch (BindException $e) {
        //     Log::error('LDAP Bind Exception: ' . $e->getMessage());
        //     return back()->with('error', 'Could not connect to LDAP server. Please check your credentials or server configuration.');
        // } 
        
        // // Search for the user
        // $ldap_user = User::where('uid', '=', $username)->first();
        // if (!$ldap_user) {
        //     return redirect()->back()->with('error', 'Invalid credentials');
        // }
        
        // // Attempt to bind with the user's credentials
        // $isAuthenticated = $ldap->auth()->attempt($ldap_user, $password);
        // if($isAuthenticated){
        //     auth()->login($app_user);
        //     session()->regenerate();
        // }
        // else{
        //     return redirect()->back()->with('error', 'Invalid credentials');
        // }
        auth()->login($app_user);
        session()->regenerate();
        try{
            MessageSent::dispatch("$app_user->username logged in", 'system');
        } catch (\Exception $e) {
            
        }
        Log::info('User logged in.');

        return redirect()->intended(route('dashboard', absolute: false))->with('success', "Welcome ".$app_user->username);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        try{
            MessageSent::dispatch(auth()->user()->username." logged out", 'system');
        } catch (\Exception $e) {
            
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
