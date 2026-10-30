<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * Example team and tasks so the board is not empty on first look.
 * Runs only when SEED_DEMO=true.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $team = [];
        foreach ([
            ['manager', 'მარკეტინგის ხელმძღვანელი', '#D83A52'],
            ['smm', 'SMM მენეჯერი', '#1F76C2'],
            ['designer', 'დიზაინერი', '#6E3FD1'],
            ['copywriter', 'კოპირაიტერი', '#00854D'],
            ['photographer', 'ფოტოგრაფი', '#B4400A'],
        ] as [$role, $name, $color]) {
            $team[$role] = User::firstOrCreate(
                ['email' => $role.'@demo.local'],
                ['name' => $name, 'password' => 'demo-password', 'role' => $role, 'avatar_color' => $color],
            );
        }

        $batumi = Venue::where('slug', 'batumi')->firstOrFail();
        $kutaisi = Venue::where('slug', 'kutaisi')->firstOrFail();
        $monday = today()->startOfWeek();

        $rows = [
            [$batumi, 'instagram', 'Reels', 'ბოშური საღამო — ანონსი', 'designer', 'review', 1],
            [$batumi, 'facebook', 'პოსტერი', 'ია თომაშის საღამო', 'designer', 'review', 3],
            [$batumi, 'story', '3 სთორი', 'გიორგი ფრუიძის საღამო', 'designer', 'in_progress', 2],
            [$batumi, 'instagram', 'კარუსელი', 'ახალი მენიუს ფოტოები', 'photographer', 'todo', 4],
            [$batumi, 'facebook', 'პოსტი', 'კვირის განრიგი', 'copywriter', 'approved', 0],
            [$kutaisi, 'story', 'სთორი', 'ინტერიერის სთორი', 'designer', 'revision', 0],
            [$kutaisi, 'instagram', 'პოსტი', 'სტუმრების შეფასებები', 'smm', 'review', 1],
            [$kutaisi, 'facebook', 'პოსტერი', 'კვირის აფიშა', null, 'todo', 2],
            [$kutaisi, 'story', 'სთორი', 'დღის კერძი', 'photographer', 'todo', 3],
            [$kutaisi, 'facebook', 'პოსტი', 'კვირის მენიუ', 'copywriter', 'published', 0],
        ];

        foreach ($rows as [$venue, $platform, $format, $title, $role, $status, $day]) {
            Task::firstOrCreate(
                ['venue_id' => $venue->id, 'title' => $title],
                [
                    'platform' => $platform,
                    'format' => $format,
                    'status' => $status,
                    'assignee_id' => $role ? $team[$role]->id : null,
                    'creator_id' => $team['smm']->id,
                    'due_on' => $monday->copy()->addDays($day),
                    'publish_at' => $monday->copy()->addDays($day)->setTime(19, 0),
                ],
            );
        }

        Contact::firstOrCreate(['name' => 'გიორგი ფრუიძე'], ['venue_id' => $batumi->id, 'type' => 'artist']);
        Contact::firstOrCreate(['name' => 'ია თომაში'], ['venue_id' => $batumi->id, 'type' => 'artist']);
    }
}
