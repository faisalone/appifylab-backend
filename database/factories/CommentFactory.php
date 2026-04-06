<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Faker\Factory as FakerFactory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
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
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => $faker->sentence($faker->numberBetween(4, 12)),
        ];
    }
}
