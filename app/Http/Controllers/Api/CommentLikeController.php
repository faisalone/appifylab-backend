<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentLikeController extends Controller
{
    public function index(Request $request, Comment $comment): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $comment->loadMissing('post');
        $this->ensureCommentVisible($comment, $viewer);

        $comment->load('likes.user:id,first_name,last_name,email');

        return response()->json([
            'data' => $comment->likes->map(fn ($like): array => [
                'id' => $like->user->id,
                'first_name' => $like->user->first_name,
                'last_name' => $like->user->last_name,
                'full_name' => $like->user->full_name,
                'email' => $like->user->email,
            ])->values(),
        ]);
    }

    public function store(Request $request, Comment $comment): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $comment->loadMissing('post');
        $this->ensureCommentVisible($comment, $viewer);

        $comment->likes()->firstOrCreate([
            'user_id' => $viewer->id,
        ]);

        $comment->load('likes.user:id,first_name,last_name,email');

        return response()->json([
            'message' => 'Comment liked successfully.',
            'data' => [
                'likes_count' => $comment->likes->count(),
                'liked_by_me' => true,
                'liked_by' => $comment->likes->map(fn ($like): array => [
                    'id' => $like->user->id,
                    'first_name' => $like->user->first_name,
                    'last_name' => $like->user->last_name,
                    'full_name' => $like->user->full_name,
                    'email' => $like->user->email,
                ])->values(),
            ],
        ]);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $comment->loadMissing('post');
        $this->ensureCommentVisible($comment, $viewer);

        $comment->likes()->where('user_id', $viewer->id)->delete();

        $comment->load('likes.user:id,first_name,last_name,email');

        return response()->json([
            'message' => 'Comment unliked successfully.',
            'data' => [
                'likes_count' => $comment->likes->count(),
                'liked_by_me' => false,
                'liked_by' => $comment->likes->map(fn ($like): array => [
                    'id' => $like->user->id,
                    'first_name' => $like->user->first_name,
                    'last_name' => $like->user->last_name,
                    'full_name' => $like->user->full_name,
                    'email' => $like->user->email,
                ])->values(),
            ],
        ]);
    }

    private function ensureCommentVisible(Comment $comment, User $viewer): void
    {
        if ($comment->post->visibility === 'private' && $comment->post->user_id !== $viewer->id) {
            abort(403, 'You are not allowed to access this comment.');
        }
    }
}
