<?php

namespace App\Modules\GitHub\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\GitHub\Exceptions\GitHubConnectionException;
use App\Modules\GitHub\Services\GitHubConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GitHubConnectionCallbackController extends Controller
{
    public function __invoke(Request $request, GitHubConnectionService $connections): RedirectResponse
    {
        $clientUrl = rtrim((string) config('releaselens.client_url'), '/');

        if (! $request->filled('state') || ! $request->filled('installation_id')) {
            return redirect()->away("{$clientUrl}/app?github=cancelled");
        }

        try {
            /** @var User $user */
            $user = $request->user();
            $connections->complete(
                $user,
                $request->string('state')->toString(),
                $request->integer('installation_id'),
                $request,
            );

            return redirect()->away("{$clientUrl}/app?github=connected");
        } catch (GitHubConnectionException $exception) {
            report($exception);

            return redirect()->away(
                "{$clientUrl}/app?github=".rawurlencode(strtolower($exception->errorCode)),
            );
        }
    }
}
