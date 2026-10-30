<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Safe to run more than once: creates the two houses and the first admin
     * account only if they do not exist yet.
     */
    public function run(): void
    {
        Venue::firstOrCreate(['slug' => 'batumi'], [
            'name' => 'ბათუმის სახლში',
            'color' => '#0B7A83',
            'sort' => 1,
            'concept' => "მისამართი: ბათუმი, ფარნავაზ მეფის 69.\nსაღამოები: ბოშური საღამო, გიორგი ფრუიძე, ია თომაში.\nკონტენტი ქართულად და ინგლისურად; CTA — მაგიდის დაჯავშნა.",
        ]);

        Venue::firstOrCreate(['slug' => 'kutaisi'], [
            'name' => 'ქუთაისის სახლში',
            'color' => '#B4400A',
            'sort' => 2,
            'concept' => "ქუთაისის სახლში — ცალკე მენიუ და ინფორმაცია.\nDJ არ არის.\nკონტენტი ქართულად და ინგლისურად.",
        ]);

        $email = env('ADMIN_EMAIL', 'admin@example.com');

        if (! User::where('email', $email)->exists()) {
            User::create([
                'name' => env('ADMIN_NAME', 'ადმინისტრატორი'),
                'email' => $email,
                'password' => env('ADMIN_PASSWORD', 'change-me-now'),
                'role' => 'admin',
                'avatar_color' => '#6E3FD1',
            ]);
        }

        if (env('SEED_DEMO', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
