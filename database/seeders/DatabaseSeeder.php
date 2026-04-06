<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demoUser = User::factory()->create([
            'first_name' => 'Test2',
            'last_name' => 'User',
            'email' => 'test2@example.com',
            'profile_image_url' => User::defaultProfileImageUrl('Test2', 'User'),
            'password' => 'password',
        ]);

        User::factory(8)->create();

        /** @var Collection<int, User> $users */
        $users = User::query()->get();

        // Ensure the demo account has data to immediately interact with after login.
        $seededPosts = collect();

        Post::factory(3)->for($demoUser)->create([
            'visibility' => 'public',
        ])->each(fn (Post $post) => $seededPosts->push($post));

        Post::factory(27)->create()->each(fn (Post $post) => $seededPosts->push($post));

        $seededPosts->each(function (Post $post) use ($users): void {
            $postLikerIds = $this->randomUserIds($users, 6, [$post->user_id]);

            foreach ($postLikerIds as $likerId) {
                $post->likes()->create(['user_id' => $likerId]);
            }

            $rootCommentCount = random_int(1, 5);

            for ($i = 0; $i < $rootCommentCount; $i++) {
                /** @var User $commentAuthor */
                $commentAuthor = $users->random();
                $commentCreatedAt = $post->created_at->copy()->addMinutes(random_int(5, 1200));

                $comment = Comment::factory()->create([
                    'post_id' => $post->id,
                    'user_id' => $commentAuthor->id,
                    'parent_id' => null,
                    'created_at' => $commentCreatedAt,
                    'updated_at' => $commentCreatedAt,
                ]);

                $commentLikerIds = $this->randomUserIds($users, 4, [$comment->user_id]);

                foreach ($commentLikerIds as $likerId) {
                    $comment->likes()->create(['user_id' => $likerId]);
                }

                $replyCount = random_int(0, 2);

                for ($r = 0; $r < $replyCount; $r++) {
                    /** @var User $replyAuthor */
                    $replyAuthor = $users->random();
                    $replyCreatedAt = $comment->created_at->copy()->addMinutes(random_int(1, 360));

                    $reply = Comment::factory()->create([
                        'post_id' => $post->id,
                        'user_id' => $replyAuthor->id,
                        'parent_id' => $comment->id,
                        'created_at' => $replyCreatedAt,
                        'updated_at' => $replyCreatedAt,
                    ]);

                    $replyLikerIds = $this->randomUserIds($users, 3, [$reply->user_id]);

                    foreach ($replyLikerIds as $likerId) {
                        $reply->likes()->create(['user_id' => $likerId]);
                    }
                }
            }
        });
    }

    /**
     * @param Collection<int, User> $users
     * @param list<int> $excludeIds
     *
     * @return list<int>
     */
    private function randomUserIds(Collection $users, int $maxCount, array $excludeIds = []): array
    {
        $pool = $users->whereNotIn('id', $excludeIds)->values();

        if ($pool->isEmpty()) {
            return [];
        }

        $take = random_int(0, min($maxCount, $pool->count()));

        if ($take === 0) {
            return [];
        }

        return $pool
            ->shuffle()
            ->take($take)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
