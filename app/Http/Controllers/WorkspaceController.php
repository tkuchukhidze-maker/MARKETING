<?php

namespace App\Http\Controllers;

use App\Models\TaskAssignment;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "ჩემი სივრცე" — one person's own work, separate from the shared board:
 * new (to read) → in progress → ready (waiting for approval).
 */
class WorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $venues = Venue::orderBy('sort')->get();
        $venue = $venues->firstWhere('slug', $request->query('venue'));

        $mine = TaskAssignment::query()
            ->where('user_id', $user->id)
            ->whereHas('task', fn ($q) => $q
                ->whereNotIn('status', ['approved', 'published'])
                ->when($venue, fn ($q) => $q->where('venue_id', $venue->id)))
            ->with(['task.venue', 'task.channels', 'task.assignments.user'])
            ->get()
            // urgent first: sent back for changes, then nearest deadline, then oldest
            ->sortBy(fn (TaskAssignment $a) => [
                $a->task->status === 'revision' ? 0 : 1,
                $a->task->due_on?->timestamp ?? PHP_INT_MAX,
                $a->id,
            ])
            ->values();

        $columns = collect(TaskAssignment::STATUSES)->map(fn ($def, $key) => [
            'key' => $key,
            'label' => $def[0],
            'color' => $def[1],
            'items' => $mine->where('status', $key)->values(),
        ]);

        $done = TaskAssignment::query()->where('user_id', $user->id)
            ->whereHas('task', fn ($q) => $q->whereIn('status', ['approved', 'published']))
            ->with('task.venue')->latest('ready_at')->limit(6)->get();

        return view('workspace', compact('columns', 'venues', 'venue', 'done'));
    }
}
