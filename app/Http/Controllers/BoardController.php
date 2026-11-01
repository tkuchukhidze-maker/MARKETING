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
    public function home(Request $request): RedirectResponse
    {
        // makers land in their own space; managers on the shared board
        if (! $request->user()->canApprove()) {
            return redirect()->route('workspace');
        }

        $venue = Venue::studio()->firstOrFail();

        return redirect()->route('board', $venue);
    }

    public function board(Request $request, Venue $venue, Insights $insights): View
    {
        abort_unless($venue->in_studio, 404);
        $platform = $request->query('platform');
        $platform = array_key_exists((string) $platform, Studio::PLATFORMS) ? $platform : null;
        $mine = $request->boolean('mine');
        $q = trim((string) $request->query('q'));
        $view = $request->query('view') === 'list' ? 'list' : 'board';

        $all = $venue->tasks()->with(['assignments.user', 'channels.publisher'])->withCount(['comments', 'assets'])
            ->orderByRaw('due_on is null')->orderBy('due_on')->orderBy('publish_at')->orderBy('id')
            ->get();

        $tasks = $all
            ->when($platform, fn ($c) => $c->filter(fn (Task $t) => $t->hasPlatform($platform)))
            ->when($mine, fn ($c) => $c->filter(fn (Task $t) => $t->assignmentFor($request->user()) !== null))
            ->when($q !== '', fn ($c) => $c->filter(fn (Task $t) => mb_stripos($t->title, $q) !== false));

        // list view: what needs the manager's eye first
        $groups = collect(Studio::BOARD_ORDER)->map(fn ($key) => [
            'key' => $key,
            'label' => Studio::STATUSES[$key][0],
            'color' => Studio::STATUSES[$key][1],
            'open' => ! in_array($key, ['in_progress', 'published'], true),
            'tasks' => $tasks->where('status', $key)->values(),
        ]);

        // board view: the whole pipeline left to right, everyone sees every stage
        $byPublish = fn ($c) => $c->sortBy(fn (Task $t) => $t->publish_at?->timestamp ?? PHP_INT_MAX)->values();
        $doneSince = today()->subDays(Studio::DONE_DAYS);
        $published = $tasks->where('status', 'published')
            ->sortByDesc(fn (Task $t) => $t->channels->max('published_at')?->timestamp ?? $t->updated_at->timestamp)
            ->values();
        $recent = $published->filter(fn (Task $t) => ($t->channels->max('published_at') ?? $t->updated_at)->gte($doneSince))->values();

        $columns = collect([
            ['key' => 'todo', 'label' => 'გასაკეთებელი', 'hint' => 'ახალი დავალებები', 'empty' => 'ახალი დავალება არ არის.', 'color' => '#676879',
                'tasks' => $tasks->where('status', 'todo')->values()],
            ['key' => 'in_progress', 'label' => 'მუშავდება', 'hint' => 'შესწორებებიც აქაა', 'empty' => 'ახლა არავინ მუშაობს.', 'color' => '#1F76C2',
                'tasks' => $tasks->whereIn('status', ['revision', 'in_progress'])->sortBy(fn (Task $t) => $t->status === 'revision' ? 0 : 1)->values()],
            ['key' => 'review', 'label' => 'დასადასტურებელი', 'hint' => 'ელოდება მენეჯერს', 'empty' => 'დასადასტურებელი არაფერია.', 'color' => '#E0A800',
                'tasks' => $tasks->where('status', 'review')->values()],
            ['key' => 'approved', 'label' => 'განსათავსებელი', 'hint' => 'დადასტურდა — უნდა დაიდოს', 'empty' => 'განსათავსებელი არაფერია.', 'color' => '#00854D',
                'tasks' => $byPublish($tasks->where('status', 'approved'))],
            ['key' => 'published', 'label' => 'განთავსდა', 'hint' => 'ბოლო '.Studio::DONE_DAYS.' დღე', 'empty' => 'ბოლო დღეებში არაფერი განთავსებულა.', 'color' => '#6E3FD1',
                'tasks' => $recent, 'older' => $published->count() - $recent->count()],
        ]);

        // the pulse: counted on the whole venue so the numbers don't jump with filters
        $toPost = $all->where('status', 'approved');
        $weekStart = today()->startOfWeek();
        $pulse = [
            'review' => $all->where('status', 'review')->count(),
            'to_post' => $toPost->count(),
            'post_today' => $toPost->filter(fn (Task $t) => $t->publish_at?->isToday())->count(),
            'post_late' => $toPost->filter(fn (Task $t) => $t->publish_at?->isPast())->count(),
            'working' => $all->whereIn('status', ['todo', 'in_progress', 'revision'])->count(),
            'revision' => $all->where('status', 'revision')->count(),
            'overdue' => $all->filter->isOverdue()->count(),
            'live_week' => $all->flatMap->channels->filter(fn ($c) => $c->published_at?->gte($weekStart))->count(),
        ];

        $platformCounts = collect(Studio::PLATFORMS)->map(fn ($d, $k) => $all->filter(fn (Task $t) => $t->hasPlatform($k))->count());

        return view('board', [
            'venue' => $venue,
            'view' => $view,
            'groups' => $groups,
            'columns' => $columns,
            'pulse' => $pulse,
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
        abort_unless($venue->in_studio, 404);
        try {
            $start = Carbon::parse((string) $request->query('week', today()->toDateString()))->startOfWeek();
        } catch (\Throwable) {
            $start = today()->startOfWeek();
        }
        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));

        $tasks = $venue->tasks()->with(['assignments.user', 'channels'])
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
                        'live' => (bool) $t->channels->firstWhere('platform', $platform)?->isPublished(),
                    ])
                    ->values()),
            ];
        });

        $unscheduled = $venue->tasks()->with('channels')->open()->whereNull('publish_at')->latest()->limit(8)->get();

        return view('calendar', compact('venue', 'days', 'grid', 'start', 'unscheduled'));
    }
}
