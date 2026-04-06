<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Faker\Factory as FakerFactory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = FakerFactory::create();

        return [
            'user_id' => User::factory(),
            'body' => $faker->paragraph($faker->numberBetween(1, 3)),
            'image_path' => null,
            'visibility' => $faker->boolean(75) ? 'public' : 'private',
        ];
    }
}
