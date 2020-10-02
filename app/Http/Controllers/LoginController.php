<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    public function login()
    {
        return Socialite::driver('github')
                        ->scopes('repo')
                        ->redirect();
    }

    public function callback(Request $request)
    {
        if ($request->missing('code')) {
            return redirect('/');
        }

        /**
         * @var \Laravel\Socialite\Two\User $user
         */
        $user = Socialite::driver('github')->user();

        /**
         * @var \App\Models\User $loginUser
         */
        $loginUser = User::updateOrCreate(
            [
                'id' => $user->id,
            ],
            [
                'name'         => $user->nickname,
                'email'        => $user->email,
                'github_token' => $user->token,
                'expired_at'   => now()->addMonths(3),
            ]
        );

        auth()->login($loginUser, true);

        return redirect('home');
    }

    public function logout()
    {
        auth()->logout();

        return redirect('/');
    }
}
