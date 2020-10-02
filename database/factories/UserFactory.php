<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'id'                => $this->faker->randomDigitNotNull,
            'name'              => $this->faker->name,
            'email'             => $this->faker->unique()->safeEmail,
            'github_token'      => Str::random(10),
            'gitlab_token'      => Str::random(10),
            'expired_at'        => now()->addMonth(),
            'email_verified_at' => null,
            'remember_token'    => Str::random(10),
        ];
    }
}
