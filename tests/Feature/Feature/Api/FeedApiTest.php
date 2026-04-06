<?php

namespace Tests\Feature\Feature\Api;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_returns_public_posts_and_own_private_posts_only(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();

        $authorPublic = Post::create([
            'user_id' => $author->id,
            'body' => 'author-public',
            'visibility' => 'public',
        ]);

        $authorPrivate = Post::create([
            'user_id' => $author->id,
            'body' => 'author-private',
            'visibility' => 'private',
        ]);

        $otherPublic = Post::create([
            'user_id' => $otherUser->id,
            'body' => 'other-public',
            'visibility' => 'public',
        ]);

        $otherPrivate = Post::create([
            'user_id' => $otherUser->id,
            'body' => 'other-private',
            'visibility' => 'private',
        ]);

        $authorResponse = $this->actingAs($author, 'api')->getJson('/api/posts');
        $authorIds = collect($authorResponse->json('data'))->pluck('id');

        $authorResponse->assertOk();
        $this->assertTrue($authorIds->contains($authorPublic->id));
        $this->assertTrue($authorIds->contains($authorPrivate->id));
        $this->assertTrue($authorIds->contains($otherPublic->id));
        $this->assertFalse($authorIds->contains($otherPrivate->id));

        $otherResponse = $this->actingAs($otherUser, 'api')->getJson('/api/posts');
        $otherIds = collect($otherResponse->json('data'))->pluck('id');

        $otherResponse->assertOk();
        $this->assertTrue($otherIds->contains($authorPublic->id));
        $this->assertFalse($otherIds->contains($authorPrivate->id));
        $this->assertTrue($otherIds->contains($otherPublic->id));
        $this->assertTrue($otherIds->contains($otherPrivate->id));
    }

    public function test_post_comment_reply_and_like_workflow(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();

        $post = Post::create([
            'user_id' => $author->id,
            'body' => 'Hello world',
            'visibility' => 'public',
        ]);

        $this->actingAs($viewer, 'api')
            ->postJson("/api/posts/{$post->id}/like", [])
            ->assertOk();

        $this->assertDatabaseHas('likes', [
            'user_id' => $viewer->id,
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
        ]);

        $commentResponse = $this->actingAs($viewer, 'api')->postJson(
            "/api/posts/{$post->id}/comments",
            ['body' => 'Nice post'],
        );
        $commentResponse->assertCreated();

        $commentId = (int) $commentResponse->json('data.id');

        $replyResponse = $this->actingAs($author, 'api')->postJson(
            "/api/comments/{$commentId}/replies",
            ['body' => 'Thanks!'],
        );
        $replyResponse->assertCreated();

        $replyId = (int) $replyResponse->json('data.id');

        $this->actingAs($author, 'api')
            ->postJson("/api/comments/{$commentId}/like", [])
            ->assertOk();

        $this->actingAs($viewer, 'api')
            ->postJson("/api/comments/{$replyId}/like", [])
            ->assertOk();

        $this->assertDatabaseHas('likes', [
            'user_id' => $author->id,
            'likeable_type' => Comment::class,
            'likeable_id' => $commentId,
        ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $viewer->id,
            'likeable_type' => Comment::class,
            'likeable_id' => $replyId,
        ]);

        $feedResponse = $this->actingAs($viewer, 'api')->getJson('/api/posts');
        $feedResponse->assertOk();

        $firstPost = collect($feedResponse->json('data'))->firstWhere('id', $post->id);
        $this->assertNotNull($firstPost);
        $this->assertSame(1, $firstPost['likes_count']);
        $this->assertSame(1, $firstPost['comments_count']);
    }

}
