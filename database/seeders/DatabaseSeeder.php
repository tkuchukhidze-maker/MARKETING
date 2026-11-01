<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

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

        // reporting-only brand: in the CMO reports, not on the social media board
        // (only once the reporting migration has run — never break a deploy)
        if (Schema::hasColumn('venues', 'in_studio')) Venue::firstOrCreate(['slug' => 'magnolia'], [
            'name' => 'Magnolia Hotel',
            'color' => '#7A4E9C',
            'sort' => 3,
            'in_studio' => false,
            'kind' => 'hotel',
            'gross_margin' => 0.70,
            'concept' => 'Magnolia Hotel Tbilisi — სასტუმრო. მხოლოდ CMO რეპორტინგში.',
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
