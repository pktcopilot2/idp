<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeaderUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // GET https://leader.pupukkaltim.com/api/Api_leader/get_all_karyawan
        // HEADER = Api-Key : LEMVwsRTN20Pq7PP0VTh
        // {
        //     "code": 1,
        //     "message": "Success",
        //     "data": {
        //         "API_REQUEST": "GET ALL KARYAWAN",
        //         "USERS_COUNT": 4596,
        //         "USERS_LIST": [
        //         {
        //             "USERS_NPK": "4244763",
        //             "USERS_EMAIL": "4244763@pupukkaltim.com",
        //             "USERS_NAME": "A J I''jazurrohman",
        //             "USERS_USERNAME": "4244763",
        //             "USERS_HIERARCHY_CODE": "13434000HGJDFN",
        //             "USERS_ID_POSISI": "50118407",
        //             "USERS_POSISI": "Jr Engineer Maintenance Strategy",
        //             "USERS_ID_UNIT_KERJA": "D002450000",
        //             "USERS_UNIT_KERJA": "Departemen Keandalan Pabrik",
        //             "USERS_FLAG": "TKO",
        //             "USERS_ALIAS": "4244763",
        //             "USERS_GRADE": "5A"
        //         },
        //         {
        //             "USERS_NPK": "4114001",
        //             "USERS_EMAIL": "4114001@pupukkaltim.com",
        //             "USERS_NAME": "Aan Fajar Permana",
        //             "USERS_USERNAME": "4114001",
        //             "USERS_HIERARCHY_CODE": "13121111KJFDFS",
        //             "USERS_ID_POSISI": "50018963",
        //             "USERS_POSISI": "Sr Operator Ammonia P5 shift",
        //             "USERS_ID_UNIT_KERJA": "D002140000",
        //             "USERS_UNIT_KERJA": "Departemen Operasi Pabrik 5",
        //             "USERS_FLAG": "TKO",
        //             "USERS_ALIAS": "4114001",
        //             "USERS_GRADE": "12"
        //         }
        //     }
        // }

        try {
            $response = Http::withHeaders([
                'Api-Key' => 'LEMVwsRTN20Pq7PP0VTh',
            ])->get('https://leader.pupukkaltim.com/api/Api_leader/get_all_karyawan');

            if ($response->successful()) {
                $users = $response->json('data.USERS_LIST', []);

                foreach ($users as $userData) {
                    // Create or update user in the database
                    $user = User::updateOrCreate(
                        ['username' => $userData['USERS_USERNAME']],
                        [
                            'name' => $userData['USERS_NAME'],
                            'email' => $userData['USERS_EMAIL'],
                            // You can set a default password or use a random one since users will log in via SSO
                            'password' => bcrypt(Str::random(16)),
                        ]
                    );

                    // Optionally, assign roles or permissions based on user data
                    // For example, you could assign a "Leader" role to all users from this API
                    $user->assignRole('Leader');
                }

                Log::info('Successfully seeded leader users from API.');
            } else {
                Log::error('Failed to fetch leader users from API. Status: ' . $response->status());
            }
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the API request or database operations
            Log::error('Error seeding leader users: ' . $e->getMessage());
        }
    }
}
