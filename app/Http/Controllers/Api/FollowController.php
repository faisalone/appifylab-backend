<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $followingUsers = Follow::query()
            ->where('follower_id', $viewer->id)
            ->with('following:id,first_name,last_name,email,profile_image_url')
            ->latest('created_at')
            ->get()
            ->map(fn (Follow $follow): array => $this->serializeUser($follow->following))
            ->filter(fn (array $user): bool => ! empty($user))
            ->values();

        return response()->json([
            'data' => $followingUsers,
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        if ($viewer->id === $user->id) {
            return response()->json([
                'message' => 'You cannot follow yourself.',
            ], 422);
        }

        Follow::firstOrCreate([
            'follower_id' => $viewer->id,
            'following_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'User followed successfully.',
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        Follow::where('follower_id', $viewer->id)
            ->where('following_id', $user->id)
            ->delete();

        return response()->json([
            'message' => 'User unfollowed successfully.',
        ]);
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
