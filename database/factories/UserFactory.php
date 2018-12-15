<?php

use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(App\Model\User::class, function (Faker $faker) {
    return [
        'id'                => $faker->randomDigitNotNull,
        'name'              => $faker->name,
        'email'             => $faker->unique()->safeEmail,
        'github_token'      => str_random(10),
        'gitlab_token'      => str_random(10),
        'expired_at'        => now()->addMonth(),
        'email_verified_at' => null,
        'remember_token'    => str_random(10),
    ];
});
