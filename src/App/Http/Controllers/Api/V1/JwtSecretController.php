<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags JWT Secret
 *
 * Manages the account-wide signing secret used to sign end-user chat JWTs.
 * One secret applies to every agent the account owns; rotating it
 * invalidates outstanding JWTs across all of them at once.
 *
 * Master token required (ability `agents:manage`).
 */
class JwtSecretController
{
    /**
     * Get JWT secret status
     *
     * Returns whether a secret is configured — but never the plaintext. The
     * plaintext is only ever returned by `POST /jwt-secret`.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->refresh();

        return response()->json(['configured' => $user->jwt_secret !== null]);
    }

    /**
     * Generate or rotate JWT secret
     *
     * Returns the new plaintext ONCE. Store it immediately — there is no way
     * to retrieve it again after this response. Any JWT signed with the
     * previous secret becomes invalid the moment this endpoint returns.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'jwt_secret' => $user->rotateJwtSecret(),
        ], 201);
    }
}
