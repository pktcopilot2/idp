<?php

namespace App\Http\Controllers;

use App\Features\EmailMfa;
use App\Features\TwoFactorAuthentication as TwoFactorAuthenticationFeature;
use App\Features\WhatsAppMfa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class FeatureFlagController extends Controller
{
    /**
     * Registered feature flags with their metadata.
     *
     * @var array<string, array{class: string, label: string, description: string}>
     */
    private const FEATURES = [
        'two-factor-authentication' => [
            'class' => TwoFactorAuthenticationFeature::class,
            'label' => 'Two-Factor Authentication',
            'description' => 'TOTP-based two-factor authentication via authenticator app.',
        ],
        'email-mfa' => [
            'class' => EmailMfa::class,
            'label' => 'Email MFA',
            'description' => 'One-time verification code sent to the user\'s email address at each login.',
        ],
        'whatsapp-mfa' => [
            'class' => WhatsAppMfa::class,
            'label' => 'WhatsApp MFA',
            'description' => 'One-time verification code sent via WhatsApp at each login.',
        ],
    ];

    /**
     * How Pennant serializes the null (global) scope.
     */
    private const GLOBAL_SCOPE = '__laravel_null';

    /**
     * Prefix used when serializing User model scopes.
     */
    private const USER_SCOPE_PREFIX = 'App\\Models\\User|';

    /**
     * Display all feature flags with their stored overrides.
     */
    public function index(): Response
    {
        $featureClasses = array_column(self::FEATURES, 'class');

        $storedFeatures = DB::table('features')
            ->whereIn('name', $featureClasses)
            ->get();

        // Collect unique user IDs from user-scoped rows
        $userIds = $storedFeatures
            ->filter(fn ($r) => $r->scope !== self::GLOBAL_SCOPE)
            ->map(fn ($r) => $this->parseScopeToUserId($r->scope))
            ->filter()
            ->unique()
            ->values();

        $usersById = $userIds->isNotEmpty()
            ? User::whereIn('id', $userIds)->get(['id', 'name', 'username'])->keyBy('id')
            : collect();

        $features = collect(self::FEATURES)->map(function ($meta, $slug) use ($storedFeatures, $usersById) {
            $stored = $storedFeatures->where('name', $meta['class']);

            $globalRow = $stored->firstWhere('scope', self::GLOBAL_SCOPE);
            $userRows = $stored->filter(fn ($r) => $r->scope !== self::GLOBAL_SCOPE);

            return [
                'slug' => $slug,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'global' => $globalRow !== null ? (bool) json_decode($globalRow->value) : null,
                'overrides' => $userRows->map(function ($row) use ($usersById) {
                    $userId = $this->parseScopeToUserId($row->scope);
                    $user = $userId ? $usersById->get($userId) : null;

                    return [
                        'scope' => $row->scope,
                        'user_id' => $userId,
                        'user' => $user
                            ? ['id' => $user->id, 'name' => $user->name, 'username' => $user->username]
                            : null,
                        'enabled' => (bool) json_decode($row->value),
                    ];
                })->values(),
            ];
        })->values();

        $allUsers = User::select('id', 'name', 'username')->orderBy('name')->get();

        return Inertia::render('features/Index', [
            'features' => $features,
            'users' => $allUsers,
        ]);
    }

    /**
     * Update (activate / deactivate) a feature for a given scope.
     */
    public function update(Request $request, string $feature): RedirectResponse
    {
        $meta = self::FEATURES[$feature] ?? null;
        abort_if($meta === null, 404);

        $request->validate([
            'scope' => ['required', 'in:global,users'],
            'user_ids' => ['required_if:scope,users', 'nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'enabled' => ['required', 'boolean'],
        ]);

        $class = $meta['class'];
        $enabled = (bool) $request->enabled;

        if ($request->scope === 'global') {
            $enabled
                ? Feature::for(null)->activate($class)
                : Feature::for(null)->deactivate($class);
        } else {
            $users = User::whereIn('id', $request->user_ids ?? [])->get();

            foreach ($users as $user) {
                $enabled
                    ? Feature::for($user)->activate($class)
                    : Feature::for($user)->deactivate($class);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature flag updated.')]);

        return back();
    }

    /**
     * Purge all stored overrides for all managed features, reverting to default resolution.
     */
    public function purge(): RedirectResponse
    {
        $classes = array_column(self::FEATURES, 'class');

        Feature::purge($classes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All feature overrides purged.')]);

        return back();
    }

    /**
     * Remove a stored override for a feature, reverting to the default resolution.
     */
    public function destroyOverride(Request $request, string $feature): RedirectResponse
    {
        $meta = self::FEATURES[$feature] ?? null;
        abort_if($meta === null, 404);

        $request->validate([
            'scope' => ['required', 'string'],
        ]);

        $class = $meta['class'];
        $scope = $request->scope;

        if ($scope === self::GLOBAL_SCOPE) {
            Feature::for(null)->forget($class);
        } else {
            $userId = $this->parseScopeToUserId($scope);

            if ($userId && ($user = User::find($userId))) {
                Feature::for($user)->forget($class);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature override removed.')]);

        return back();
    }

    /**
     * Parse a Pennant-serialized user scope string into a user ID.
     */
    private function parseScopeToUserId(string $scope): ?int
    {
        if (str_starts_with($scope, self::USER_SCOPE_PREFIX)) {
            $id = (int) substr($scope, strlen(self::USER_SCOPE_PREFIX));

            return $id > 0 ? $id : null;
        }

        return null;
    }
}
