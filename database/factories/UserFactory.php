<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;
    protected static int $index = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstNames = ['John', 'Jane', 'Alex', 'Sam', 'Taylor', 'Chris', 'Avery', 'Jordan'];
        $lastNames = ['Smith', 'Johnson', 'Brown', 'Williams', 'Jones', 'Miller', 'Davis', 'Wilson'];

        $index = self::$index++;
        $firstName = $firstNames[$index % count($firstNames)];
        $lastName = $lastNames[$index % count($lastNames)];

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($firstName . '.' . $lastName . '.' . ($index + 1) . '@example.com'),
            'profile_image_url' => User::defaultProfileImageUrl($firstName, $lastName),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
