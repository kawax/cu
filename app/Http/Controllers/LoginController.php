<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Laravel\Socialite\Facades\Socialite;

use App\Model\User;

class LoginController extends Controller
{
    public function login()
    {
        return Socialite::driver('github')->scopes('repo')->redirect();
    }

    public function callback(Request $request)
    {
        if (!$request->has('code')) {
            return redirect('/');
        }

        /**
         * @var \Laravel\Socialite\Two\User $user
         */
        $user = Socialite::driver('github')->user();

        /**
         * @var \App\Model\User $loginUser
         */
        $loginUser = User::updateOrCreate(
            [
                'id' => $user->id,
            ],
            [
                'name'         => $user->nickname,
                'email'        => $user->email,
                'github_token' => $user->token,
                'expired_at'   => now()->addMonth(),
            ]);

        auth()->login($loginUser, true);

        return redirect('home');
    }

    public function logout()
    {
        auth()->logout();

        return redirect('/');
    }
}
