<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request, Post $post): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $this->ensurePostVisible($post, $viewer);

        $perPage = min((int) $request->integer('per_page', 10), 50);
        $excludeCommentId = (int) $request->integer('exclude_comment_id', 0);

        $commentsQuery = Comment::query()
            ->where('post_id', $post->id)
            ->whereNull('parent_id');

        if ($excludeCommentId > 0) {
            $commentsQuery->where('id', '!=', $excludeCommentId);
        }

        $comments = $commentsQuery
            ->latest('created_at')
            ->with([
                'user:id,first_name,last_name,email,profile_image_url',
                'likes.user:id,first_name,last_name,email,profile_image_url',
                'replies' => fn ($replyQuery) => $replyQuery
                    ->latest('created_at')
                    ->with([
                        'user:id,first_name,last_name,email,profile_image_url',
                        'likes.user:id,first_name,last_name,email,profile_image_url',
                    ]),
            ])
            ->paginate($perPage);

        return response()->json([
            'data' => $comments->getCollection()->map(
                fn (Comment $comment): array => $this->serializeComment($comment, $viewer->id, true)
            )->values(),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $this->ensurePostVisible($post, $viewer);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $viewer->id,
            'parent_id' => null,
            'body' => trim($request->string('body')->value()),
        ]);

        $comment->load([
            'user:id,first_name,last_name,email,profile_image_url',
            'likes.user:id,first_name,last_name,email,profile_image_url',
            'replies.user:id,first_name,last_name,email,profile_image_url',
            'replies.likes.user:id,first_name,last_name,email,profile_image_url',
        ]);

        return response()->json([
            'message' => 'Comment created successfully.',
            'data' => $this->serializeComment($comment, $viewer->id, true),
        ], 201);
    }

    public function reply(StoreCommentRequest $request, Comment $comment): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $comment->loadMissing('post');
        $this->ensurePostVisible($comment->post, $viewer);

        if ($comment->parent_id !== null) {
            return response()->json([
                'message' => 'Replies are only allowed on top-level comments.',
            ], 422);
        }

        $reply = Comment::create([
            'post_id' => $comment->post_id,
            'user_id' => $viewer->id,
            'parent_id' => $comment->id,
            'body' => trim($request->string('body')->value()),
        ]);

        $reply->load([
            'user:id,first_name,last_name,email,profile_image_url',
            'likes.user:id,first_name,last_name,email,profile_image_url',
        ]);

        return response()->json([
            'message' => 'Reply created successfully.',
            'data' => $this->serializeComment($reply, $viewer->id, false),
        ], 201);
    }

    private function ensurePostVisible(Post $post, User $viewer): void
    {
        if ($post->visibility === 'private' && $post->user_id !== $viewer->id) {
            abort(403, 'You are not allowed to access this post.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeComment(Comment $comment, int $viewerId, bool $includeReplies): array
    {
        $data = [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'parent_id' => $comment->parent_id,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
            'author' => $this->serializeUser($comment->user),
            'likes_count' => $comment->likes->count(),
            'liked_by_me' => $comment->likes->contains('user_id', $viewerId),
            'liked_by' => $comment->likes->map(fn ($like): array => $this->serializeUser($like->user))->values(),
        ];

        if ($includeReplies) {
            $data['replies'] = $comment->replies
                ->map(fn (Comment $reply): array => $this->serializeComment($reply, $viewerId, false))
                ->values();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'email' => $user->email,
            'profile_image_url' => $user->profile_image_url,
        ];
    }
}
