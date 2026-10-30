<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use App\Services\Insights;
use App\Support\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BoardController extends Controller
{
    public function home(): RedirectResponse
    {
        $venue = Venue::orderBy('sort')->firstOrFail();

        return redirect()->route('board', $venue);
    }

    public function board(Request $request, Venue $venue, Insights $insights): View
    {
        $platform = $request->query('platform');
        $platform = array_key_exists((string) $platform, Studio::PLATFORMS) ? $platform : null;
        $mine = $request->boolean('mine');
        $q = trim((string) $request->query('q'));

        $all = $venue->tasks()->with(['assignee', 'channels'])->withCount(['comments', 'assets'])
            ->orderByRaw('due_on is null')->orderBy('due_on')->orderBy('id')
            ->get();

        $tasks = $all
            ->when($platform, fn ($c) => $c->filter(fn (Task $t) => $t->hasPlatform($platform)))
            ->when($mine, fn ($c) => $c->where('assignee_id', $request->user()->id))
            ->when($q !== '', fn ($c) => $c->filter(fn (Task $t) => mb_stripos($t->title, $q) !== false));

        $groups = collect(Studio::STATUSES)->map(fn ($def, $key) => [
            'key' => $key,
            'label' => $def[0],
            'color' => $def[1],
            'tasks' => $tasks->where('status', $key)->values(),
        ]);

        $platformCounts = collect(Studio::PLATFORMS)->map(fn ($d, $k) => $all->filter(fn (Task $t) => $t->hasPlatform($k))->count());

        return view('board', [
            'venue' => $venue,
            'groups' => $groups,
            'platform' => $platform,
            'mine' => $mine,
            'q' => $q,
            'platformCounts' => $platformCounts,
            'totalCount' => $all->count(),
            'statusSummary' => collect(Studio::STATUSES)->map(fn ($d, $k) => $all->where('status', $k)->count()),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'hints' => $insights->for($venue)->take(4),
        ]);
    }

    public function calendar(Request $request, Venue $venue): View
    {
        try {
            $start = Carbon::parse((string) $request->query('week', today()->toDateString()))->startOfWeek();
        } catch (\Throwable) {
            $start = today()->startOfWeek();
        }
        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));

        $tasks = $venue->tasks()->with(['assignee', 'channels'])
            ->whereNotNull('publish_at')
            ->whereBetween('publish_at', [$start->copy()->startOfDay(), $start->copy()->addDays(6)->endOfDay()])
            ->orderBy('publish_at')
            ->get();

        // a task made for several platforms shows up in each platform's row
        $grid = collect(Studio::PLATFORMS)->map(function ($def, $platform) use ($tasks, $days) {
            $rowTasks = $tasks->filter(fn (Task $t) => $t->hasPlatform($platform));

            return [
                'key' => $platform,
                'label' => $def[0],
                'style' => Studio::platformStyle($platform),
                'count' => $rowTasks->count(),
                'days' => $days->map(fn (Carbon $day) => $rowTasks
                    ->filter(fn (Task $t) => $t->publish_at->isSameDay($day))
                    ->map(fn (Task $t) => [
                        'task' => $t,
                        'format' => $t->channels->firstWhere('platform', $platform)?->format,
                    ])
                    ->values()),
            ];
        });

        $unscheduled = $venue->tasks()->with('channels')->open()->whereNull('publish_at')->latest()->limit(8)->get();

        return view('calendar', compact('venue', 'days', 'grid', 'start', 'unscheduled'));
    }
}
