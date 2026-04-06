<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
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
            'user_id' => User::factory(),
            'body' => 'Sample post content ' . ($index + 1),
            'image_path' => null,
            'visibility' => $index % 4 === 0 ? 'private' : 'public',
        ];
    }
}
