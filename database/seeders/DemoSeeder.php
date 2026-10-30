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
            ['smm', 'სოციალური მედია', '#1F76C2'],
            ['designer', 'გრაფიკული დიზაინერი', '#6E3FD1'],
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

        $all = ['facebook' => 'პოსტერი', 'instagram' => 'პოსტი 4:5', 'story' => 'სთორი 9:16'];
        $rows = [
            [$batumi, ['instagram' => 'Reels', 'story' => 'ვიდეო სთორი'], 'ბოშური საღამო — ანონსი', 'designer', 'review', 1],
            [$batumi, $all, 'ია თომაშის საღამო', 'designer', 'review', 3],
            [$batumi, ['story' => 'სთორის სერია'], 'გიორგი ფრუიძის საღამო', 'designer', 'in_progress', 2],
            [$batumi, ['instagram' => 'კარუსელი', 'facebook' => 'კარუსელი'], 'ახალი მენიუს ფოტოები', 'photographer', 'todo', 4],
            [$batumi, ['facebook' => 'პოსტი'], 'კვირის განრიგი', 'copywriter', 'approved', 0],
            [$kutaisi, ['story' => 'სთორი 9:16'], 'ინტერიერის სთორი', 'designer', 'revision', 0],
            [$kutaisi, ['instagram' => 'პოსტი 1:1'], 'სტუმრების შეფასებები', 'smm', 'review', 1],
            [$kutaisi, $all, 'კვირის აფიშა', null, 'todo', 2],
            [$kutaisi, ['story' => 'სთორი 9:16'], 'დღის კერძი', 'photographer', 'todo', 3],
            [$kutaisi, ['facebook' => 'პოსტი'], 'კვირის მენიუ', 'copywriter', 'published', 0],
        ];

        foreach ($rows as [$venue, $channels, $title, $role, $status, $day]) {
            $task = Task::firstOrCreate(
                ['venue_id' => $venue->id, 'title' => $title],
                [
                    'status' => $status,
                    'creator_id' => $team['smm']->id,
                    'due_on' => $monday->copy()->addDays($day),
                    'publish_at' => $monday->copy()->addDays($day)->setTime(19, 0),
                ],
            );

            if ($task->wasRecentlyCreated) {
                $task->syncChannels($channels);
                $people = $role ? [$team[$role]->id => null] : [];
                if ($title === 'ია თომაშის საღამო') {
                    // one job, three people, each with their own part
                    $people = [
                        $team['designer']->id => 'პოსტერი და სთორის დიზაინი',
                        $team['photographer']->id => 'ია თომაშის ფოტოები',
                        $team['copywriter']->id => 'პოსტის ტექსტი ქართულად და ინგლისურად',
                    ];
                }
                $task->syncAssignments($people);

                // demo progress that matches each task's board status
                $task->assignments()->update(['status' => match ($status) {
                    'in_progress', 'revision' => 'in_progress',
                    'review', 'approved', 'published' => 'ready',
                    default => 'assigned',
                }]);
                $task->update(['status' => $status]);
            }
        }

        Contact::firstOrCreate(['name' => 'გიორგი ფრუიძე'], ['venue_id' => $batumi->id, 'type' => 'artist']);
        Contact::firstOrCreate(['name' => 'ია თომაში'], ['venue_id' => $batumi->id, 'type' => 'artist']);
    }
}
