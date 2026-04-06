<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostLikeController extends Controller
{
    public function index(Request $request, Post $post): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $this->ensurePostVisible($post, $viewer);

        $post->load('likes.user:id,first_name,last_name,email');

        return response()->json([
            'data' => $post->likes->map(fn ($like): array => [
                'id' => $like->user->id,
                'first_name' => $like->user->first_name,
                'last_name' => $like->user->last_name,
                'full_name' => $like->user->full_name,
                'email' => $like->user->email,
            ])->values(),
        ]);
    }

    public function store(Request $request, Post $post): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $this->ensurePostVisible($post, $viewer);

        $post->likes()->firstOrCreate([
            'user_id' => $viewer->id,
        ]);

        $post->load('likes.user:id,first_name,last_name,email');

        return response()->json([
            'message' => 'Post liked successfully.',
            'data' => [
                'likes_count' => $post->likes->count(),
                'liked_by_me' => true,
                'liked_by' => $post->likes->map(fn ($like): array => [
                    'id' => $like->user->id,
                    'first_name' => $like->user->first_name,
                    'last_name' => $like->user->last_name,
                    'full_name' => $like->user->full_name,
                    'email' => $like->user->email,
                ])->values(),
            ],
        ]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $this->ensurePostVisible($post, $viewer);

        $post->likes()->where('user_id', $viewer->id)->delete();

        $post->load('likes.user:id,first_name,last_name,email');

        return response()->json([
            'message' => 'Post unliked successfully.',
            'data' => [
                'likes_count' => $post->likes->count(),
                'liked_by_me' => false,
                'liked_by' => $post->likes->map(fn ($like): array => [
                    'id' => $like->user->id,
                    'first_name' => $like->user->first_name,
                    'last_name' => $like->user->last_name,
                    'full_name' => $like->user->full_name,
                    'email' => $like->user->email,
                ])->values(),
            ],
        ]);
    }

    private function ensurePostVisible(Post $post, User $viewer): void
    {
        if ($post->visibility === 'private' && $post->user_id !== $viewer->id) {
            abort(403, 'You are not allowed to access this post.');
        }
    }
}
