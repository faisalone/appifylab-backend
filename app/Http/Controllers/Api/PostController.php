<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePostRequest;
use App\Http\Requests\Api\UpdatePostRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $perPage = min((int) $request->integer('per_page', 10), 50);

        $posts = Post::query()
            ->visibleTo($viewer)
            ->with([
                'user:id,first_name,last_name,email,profile_image_url',
                'likes.user:id,first_name,last_name,email,profile_image_url',
            ])
            ->withCount('comments')
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);

        $data = $posts->getCollection()->map(
            fn (Post $post): array => $this->serializePost($post, $viewer->id)
        )->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('posts', 'public')
            : null;

        $body = trim((string) $request->input('body', ''));

        $post = Post::create([
            'user_id' => $viewer->id,
            'body' => $body === '' ? null : $body,
            'image_path' => $imagePath,
            'visibility' => $request->string('visibility')->value(),
        ]);

        $post->load([
            'user:id,first_name,last_name,email,profile_image_url',
            'likes.user:id,first_name,last_name,email,profile_image_url',
        ]);
        $post->loadCount('comments');

        return response()->json([
            'message' => 'Post created successfully.',
            'data' => $this->serializePost($post, $viewer->id),
        ], 201);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        if ($post->user_id !== $viewer->id) {
            return response()->json([
                'message' => 'You are not allowed to edit this post.',
            ], 403);
        }

        $body = trim((string) $request->input('body', $post->body ?? ''));
        $visibility = $request->string('visibility')->value();
        $removeImage = $request->boolean('remove_image');

        $newImagePath = $post->image_path;

        if ($request->hasFile('image')) {
            $newImagePath = $request->file('image')->store('posts', 'public');

            if ($post->image_path) {
                Storage::disk('public')->delete($post->image_path);
            }
        } elseif ($removeImage) {
            if ($post->image_path) {
                Storage::disk('public')->delete($post->image_path);
            }

            $newImagePath = null;
        }

        if ($body === '' && $newImagePath === null) {
            return response()->json([
                'message' => 'Please provide post text or an image.',
            ], 422);
        }

        $post->update([
            'body' => $body === '' ? null : $body,
            'image_path' => $newImagePath,
            'visibility' => $visibility,
        ]);

        $post->load([
            'user:id,first_name,last_name,email,profile_image_url',
            'likes.user:id,first_name,last_name,email,profile_image_url',
        ]);
        $post->loadCount('comments');

        return response()->json([
            'message' => 'Post updated successfully.',
            'data' => $this->serializePost($post, $viewer->id),
        ]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        if ($post->user_id !== $viewer->id) {
            return response()->json([
                'message' => 'You are not allowed to delete this post.',
            ], 403);
        }

        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }

        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePost(Post $post, int $viewerId): array
    {
        return [
            'id' => $post->id,
            'body' => $post->body,
            'image_url' => $post->image_path ? Storage::url($post->image_path) : null,
            'visibility' => $post->visibility,
            'created_at' => $post->created_at?->toIso8601String(),
            'author' => $this->serializeUser($post->user),
            'likes_count' => $post->likes->count(),
            'liked_by_me' => $post->likes->contains('user_id', $viewerId),
            'liked_by' => $post->likes->map(fn ($like) => $this->serializeUser($like->user))->values(),
            'comments_count' => (int) ($post->comments_count ?? 0),
            'comments' => [],
        ];
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
