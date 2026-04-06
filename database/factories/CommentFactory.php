<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected static int $index = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $index = self::$index++;

        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => 'Sample comment ' . ($index + 1),
        ];
    }
}
