<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class MentionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('redditCredential');

        return Inertia::render('Mentions', [
            'auth' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'reddit_credential' => $user->redditCredential ? [
                        'id' => $user->redditCredential->id,
                        'reddit_id' => $user->redditCredential->reddit_id,
                        'username' => $user->redditCredential->username,
                        'token_expires_at' => $user->redditCredential->token_expires_at?->toISOString(),
                    ] : null,
                ],
            ],
        ]);
    }
}
