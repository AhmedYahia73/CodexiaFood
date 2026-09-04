<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'أحمد إبراهيم', 'email' => 'ahmed@example.com'],
            ['name' => 'محمد سعيد', 'email' => 'mohamed@example.com'],
            ['name' => 'محمود فؤاد', 'email' => 'mahmoud@example.com'],
            ['name' => 'عمر خالد', 'email' => 'omar@example.com'],
            ['name' => 'منى يوسف', 'email' => 'mona@example.com'],
            ['name' => 'رانيا مصطفى', 'email' => 'rania@example.com'],
            ['name' => 'كريم عصام', 'email' => 'kareem@example.com'],
            ['name' => 'ياسمين حسن', 'email' => 'yasmin@example.com'],
            ['name' => 'مصطفى كمال', 'email' => 'mostafa@example.com'],
            ['name' => 'تامر سامي', 'email' => 'tamer@example.com'],
            ['name' => 'هبة عادل', 'email' => 'heba@example.com'],
            ['name' => 'دينا شريف', 'email' => 'dina@example.com'],
            ['name' => 'أيمن نبيل', 'email' => 'ayman@example.com'],
            ['name' => 'طارق زياد', 'email' => 'tarek@example.com'],
            ['name' => 'شريف رمزي', 'email' => 'sherif@example.com'],
            ['name' => 'نورهان فوزي', 'email' => 'nourhan@example.com'],
            ['name' => 'على سليمان', 'email' => 'ali@example.com'],
            ['name' => 'حسام فتحي', 'email' => 'hossam@example.com'],
            ['name' => 'مريم الجزار', 'email' => 'mariam@example.com'],
            ['name' => 'وليد صلاح', 'email' => 'waleed@example.com'],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
