<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Passport\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\AuthCode;
use Laravel\Passport\DeviceCode;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', '20');

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');

        $allowedSorts = ['name', 'created_at'];
        if (! in_array($sort, $allowedSorts)) {
            $sort = 'name';
        }
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query = Client::query()
            ->withCount('assignedUsers')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy($sort, $direction);

        if ($perPage === 'all') {
            $collection = $query->get();
            $count = $collection->count();
            $clients = new LengthAwarePaginator(
                $collection, $count, $count ?: 1, 1,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        } else {
            $clients = $query->paginate(max(1, (int) $perPage))->withQueryString();
        }

        return Inertia::render('clients/Index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search ?? '',
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clients/Create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $plainSecret = $request->boolean('confidential') ? Str::random(40) : null;

        $client = new Client;
        $client->name = $request->name;
        $client->redirect_uris = $request->input('redirect_uris', []);
        $client->grant_types = $request->grant_types;
        $client->login_uri = $request->input('login_uri') ?: null;
        $client->secret = $plainSecret;
        $client->revoked = false;
        $client->save();

        if ($plainSecret) {
            session()->flash('client_secret', (string) $plainSecret);
        }

        return redirect()->route('clients.show', $client);
    }

    public function show(Client $client): Response
    {
        return Inertia::render('clients/Show', [
            'client' => $client->only(['id', 'name', 'grant_types', 'redirect_uris', 'login_uri', 'revoked', 'created_at']),
            'secret' => session('client_secret'),
            'roles_count' => \Spatie\Permission\Models\Role::where('client_id', $client->id)->count(),
        ]);
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('clients/Edit', [
            'client' => $client->only(['id', 'name', 'grant_types', 'redirect_uris', 'login_uri']),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->name = $request->name;
        $client->grant_types = $request->grant_types;
        $client->redirect_uris = $request->input('redirect_uris', []);
        $client->login_uri = $request->login_uri;
        $client->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Client updated successfully.']);

        return redirect()->route('clients.show', $client);
    }

    public function destroy(Client $client): RedirectResponse
    {
        $clientName = $client->name;

        DB::transaction(function () use ($client) {
            $accessTokenIds = Token::query()
                ->where('client_id', $client->id)
                ->pluck('id');

            if ($accessTokenIds->isNotEmpty()) {
                RefreshToken::query()
                    ->whereIn('access_token_id', $accessTokenIds)
                    ->delete();
            }

            Token::query()->where('client_id', $client->id)->delete();
            AuthCode::query()->where('client_id', $client->id)->delete();
            DeviceCode::query()->where('client_id', $client->id)->delete();

            $client->assignedUsers()->detach();

            Role::where('client_id', $client->id)->get()->each->delete();
            Permission::where('client_id', $client->id)->get()->each->delete();

            $client->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Client \"{$clientName}\" deleted."]);

        return redirect()->route('clients.index');
    }
}
