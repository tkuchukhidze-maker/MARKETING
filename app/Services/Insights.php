<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Models\Venue;
use App\Support\Studio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rule-based "smart" hints shown on the board and the team page.
 * Every hint is computed from the tasks in the database — nothing invented.
 */
class Insights
{
    public const OVERLOAD_THRESHOLD = 5;

    /**
     * @return Collection<int, array{tone: string, text: string, url: ?string}>
     */
    public function for(?Venue $venue = null): Collection
    {
        $hints = collect();
        $open = Task::query()->open()->with(['venue', 'assignments'])
            ->when($venue, fn ($q) => $q->where('venue_id', $venue->id))
            ->get();

        // 1. Overdue
        foreach ($open->filter->isOverdue()->take(3) as $task) {
            $hints->push([
                'tone' => 'danger',
                'text' => "ვადა გადაცილებულია: „{$task->title}“ ({$task->venue->name}).",
                'url' => route('tasks.show', $task),
            ]);
        }

        // 2. Waiting for approval, due today or tomorrow
        $soon = $open->filter(fn (Task $t) => $t->status === 'review'
            && $t->due_on !== null
            && $t->due_on->between(today(), today()->addDay()));
        foreach ($soon->take(3) as $task) {
            $hints->push([
                'tone' => 'warning',
                'text' => "„{$task->title}“ ელოდება დადასტურებას და ვადა {$task->due_on->format('d.m')}-ია.",
                'url' => route('tasks.show', $task),
            ]);
        }

        // 3. Nobody on it
        $unassigned = $open->filter(fn (Task $t) => $t->assignments->isEmpty());
        if ($unassigned->isNotEmpty()) {
            $hints->push([
                'tone' => 'info',
                'text' => $unassigned->count().' დავალებას შემსრულებელი არ ჰყავს — მაგ. „'.$unassigned->first()->title.'“.',
                'url' => route('tasks.show', $unassigned->first()),
            ]);
        }

        // 4. Overloaded people: unfinished parts across both houses
        $load = TaskAssignment::query()
            ->where('status', '!=', 'ready')
            ->whereHas('task', fn ($q) => $q->open())
            ->selectRaw('user_id, count(*) as n')->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [self::OVERLOAD_THRESHOLD])->pluck('n', 'user_id');
        foreach (User::whereIn('id', $load->keys())->get() as $user) {
            $hints->push([
                'tone' => 'warning',
                'text' => "{$user->name}-ს {$load[$user->id]} დაუსრულებელი დავალება აქვს — განიხილე გადანაწილება.",
                'url' => route('team'),
            ]);
        }

        // 4b. Waiting on one person while the others are done
        foreach ($open->filter(fn (Task $t) => $t->assignments->count() > 1 && $t->readyCount() === $t->assignments->count() - 1)->take(2) as $task) {
            $waiting = $task->assignments->firstWhere('status', '!=', 'ready');
            $hints->push([
                'tone' => 'info',
                'text' => "„{$task->title}“ — ყველა მზადაა, ელოდება: ".(User::find($waiting->user_id)?->name ?? '—').'.',
                'url' => route('tasks.show', $task),
            ]);
        }

        // 5. Empty days per platform in the next 7 days
        foreach ($venue ? collect([$venue]) : Venue::orderBy('sort')->get() as $v) {
            $gaps = $this->emptyDays($v, today(), 7);
            foreach ($gaps as $platform => $days) {
                if (count($days) >= 3) {
                    $hints->push([
                        'tone' => 'info',
                        'text' => "{$v->name}: ".Studio::platformLabel($platform).'-ზე '.count($days).' დღე ცარიელია მომდევნო კვირაში.',
                        'url' => route('calendar', $v),
                    ]);
                }
            }
        }

        return $hints;
    }

    /**
     * Days without any scheduled post, per platform.
     *
     * @return array<string, list<string>>
     */
    public function emptyDays(Venue $venue, Carbon $from, int $days): array
    {
        $to = $from->copy()->addDays($days);
        $tasks = Task::where('venue_id', $venue->id)->with('channels')
            ->whereNotNull('publish_at')
            ->whereBetween('publish_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['id', 'publish_at']);

        $taken = [];
        foreach ($tasks as $t) {
            foreach ($t->channels as $c) {
                $taken[$c->platform][] = $t->publish_at->toDateString();
            }
        }

        $result = [];
        foreach (array_keys(Studio::PLATFORMS) as $platform) {
            for ($i = 0; $i < $days; $i++) {
                $date = $from->copy()->addDays($i)->toDateString();
                if (! in_array($date, $taken[$platform] ?? [], true)) {
                    $result[$platform][] = $date;
                }
            }
        }

        return $result;
    }
}
