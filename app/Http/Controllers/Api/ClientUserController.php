<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passport\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ClientUserController extends Controller
{
    public function assign(Client $client, User $user): JsonResponse
    {
        $user->assignedClients()->syncWithoutDetaching([$client->id]);

        return response()->json([
            'message' => "User {$user->name} assigned to client {$client->name}.",
            'data' => [
                'user_id' => $user->id,
                'client_id' => $client->id,
                'assigned' => true,
            ],
        ]);
    }

    public function detach(Client $client, User $user): JsonResponse
    {
        $user->assignedClients()->detach($client->id);

        return response()->json([
            'message' => "User {$user->name} detached from client {$client->name}.",
            'data' => [
                'user_id' => $user->id,
                'client_id' => $client->id,
                'assigned' => false,
            ],
        ]);
    }
}
