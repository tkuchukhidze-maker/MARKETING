<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAsset;
use App\Models\TaskChannel;
use App\Models\User;
use App\Models\Venue;
use App\Support\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    /**
     * Validates the task form.
     *
     * Platforms arrive as platforms[] (one or more, or all) and a format per
     * platform as formats[platform]. Reference links arrive one per line.
     *
     * Up to three people arrive as assignees[n][user_id] + assignees[n][note]
     * (their own part of the job).
     *
     * @return array{0: array<string, mixed>, 1: array<string, ?string>, 2: array<int, ?string>}
     */
    private function validateTask(Request $request): array
    {
        // drop the empty "— nobody" rows before validating
        $request->merge(['assignees' => collect($request->input('assignees', []))
            ->filter(fn ($row) => is_array($row) && filled($row['user_id'] ?? null))
            ->values()->all()]);

        $data = $request->validate([
            'assignees' => ['array', 'max:'.Task::MAX_ASSIGNEES],
            'assignees.*.user_id' => ['required', 'distinct', Rule::exists('users', 'id')->where('is_active', true)],
            'assignees.*.note' => ['nullable', 'string', 'max:300'],
            'title' => ['required', 'string', 'max:200'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['distinct', Rule::in(array_keys(Studio::PLATFORMS))],
            'formats' => ['nullable', 'array'],
            'formats.*' => ['nullable', 'string', 'max:60'],
            'brief' => ['nullable', 'string', 'max:5000'],
            'reference_links' => ['nullable', 'string', 'max:5000'],
            'due_on' => ['nullable', 'date'],
            'publish_at' => ['nullable', 'date'],
        ], [
            'platforms.required' => 'აირჩიე მინიმუმ ერთი სოციალური ქსელი.',
            'assignees.max' => 'ერთ დავალებაზე მაქსიმუმ '.Task::MAX_ASSIGNEES.' შემსრულებელი.',
            'assignees.*.user_id.distinct' => 'ერთი და იგივე ადამიანი ორჯერ არის არჩეული.',
        ]);

        $links = collect(preg_split('/\R/', (string) ($data['reference_links'] ?? '')))
            ->map(fn ($l) => trim($l))->filter()->unique()->values();

        $bad = $links->reject(fn ($l) => filter_var($l, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $l));
        if ($bad->isNotEmpty()) {
            throw ValidationException::withMessages([
                'reference_links' => 'ეს ბმული არასწორია: '.$bad->first().' (უნდა იწყებოდეს https://-ით)',
            ]);
        }
        if ($links->count() > 20) {
            throw ValidationException::withMessages(['reference_links' => 'მაქსიმუმ 20 ბმული.']);
        }

        $channels = [];
        foreach ($data['platforms'] as $platform) {
            $channels[$platform] = $data['formats'][$platform] ?? null;
        }

        $fields = collect($data)->only(['title', 'brief', 'due_on', 'publish_at'])->all();
        $fields['reference_links'] = $links->isEmpty() ? null : $links->all();

        $people = [];
        foreach ($data['assignees'] ?? [] as $row) {
            $people[(int) $row['user_id']] = $row['note'] ?? null;
        }

        return [$fields, $channels, $people];
    }

    public function store(Request $request, Venue $venue): RedirectResponse
    {
        abort_unless($venue->in_studio, 404);
        [$fields, $channels, $people] = $this->validateTask($request);

        $task = DB::transaction(function () use ($venue, $fields, $channels, $people, $request) {
            $task = $venue->tasks()->create([
                ...$fields,
                'status' => 'todo',
                'creator_id' => $request->user()->id,
            ]);
            $task->syncChannels($channels);
            $task->syncAssignments($people);

            return $task;
        });
        $task->log($request->user(), 'created');
        $this->logPeople($request->user(), $task, []);

        return redirect()->route('board', $venue)->with('ok', 'დავალება დაემატა.');
    }

    public function show(Task $task): View
    {
        $task->load(['venue', 'channels', 'assignments.user', 'creator', 'approver', 'assets.user', 'assets.channel', 'comments.user', 'activities.user']);

        return view('tasks.show', [
            'task' => $task,
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'allowed' => $task->allowedStatusesFor(request()->user()),
            'mine' => $task->assignmentFor(request()->user()),
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        [$fields, $channels, $people] = $this->validateTask($request);
        $before = $task->assignments()->pluck('user_id')->all();

        DB::transaction(function () use ($task, $fields, $channels, $people) {
            $task->update($fields);
            $task->syncChannels($channels);
            $task->syncAssignments($people);
        });
        $task->log($request->user(), 'updated');
        $this->logPeople($request->user(), $task, $before);

        return back()->with('ok', 'შენახულია.');
    }

    /** Quick status change from the board. */
    public function quick(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Studio::STATUSES))],
        ]);

        $this->changeStatus($request->user(), $task, $data['status']);

        return back();
    }

    /** "დავიწყე მუშაობა" — the person starts on their part. */
    public function start(Request $request, Task $task): RedirectResponse
    {
        $task->startWork($request->user());

        return back()->with('ok', 'მუშაობა დაიწყე — დავალება შენს სივრცეშია, „მუშავდება“ სვეტში.');
    }

    /** "მზადაა" — the person's part is done. */
    public function ready(Request $request, Task $task): RedirectResponse
    {
        $task->markReady($request->user());
        $task->refresh();

        return back()->with('ok', $task->status === 'review'
            ? 'მზადაა — დავალება დაფაზე გადავიდა „დასადასტურებელში“.'
            : 'შენი ნაწილი მზადაა. დაფაზე გადავა, როცა ყველა შემსრულებელი მონიშნავს „მზადაა“-ს ('.$task->readyCount().'/'.$task->assignments->count().').');
    }

    /** Confirm the post is live on one platform (optionally with its link). */
    public function publish(Request $request, Task $task, TaskChannel $channel): RedirectResponse
    {
        $data = $request->validate([
            'post_url' => ['nullable', 'url:https,http', 'max:2048'],
        ], ['post_url.url' => 'პოსტის ბმული არასწორია (უნდა იწყებოდეს https://-ით).']);

        $task->confirmPublished($channel, $request->user(), $data['post_url'] ?? null);
        $task->refresh();

        return back()->with('ok', $task->status === 'published'
            ? 'ყველა ქსელზე გამოქვეყნდა — დავალება დასრულებულია.'
            : Studio::platformLabel($channel->platform).': გამოქვეყნება დადასტურდა.');
    }

    public function unpublish(Request $request, Task $task, TaskChannel $channel): RedirectResponse
    {
        $task->undoPublished($channel, $request->user());

        return back()->with('ok', Studio::platformLabel($channel->platform).': გამოქვეყნება გაუქმდა.');
    }

    private function logPeople(User $by, Task $task, array $before): void
    {
        $task->load('assignments.user');
        $now = $task->assignments->pluck('user_id')->all();
        sort($before);
        sort($now);

        if ($now !== $before && $now !== []) {
            $task->log($by, 'assigned', ['to' => $task->assignments->map(fn ($a) => $a->user->name)->join(', ')]);
        }
    }

    public function approve(Request $request, Task $task): RedirectResponse
    {
        $this->changeStatus($request->user(), $task, 'approved');

        return back()->with('ok', 'დადასტურებულია.');
    }

    public function revision(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:3000']]);
        $this->changeStatus($request->user(), $task, 'revision');

        if (! empty($data['body'])) {
            $task->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        }

        return back()->with('ok', 'შესწორება მოთხოვნილია.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        abort_unless($request->user()->canApprove() || $task->creator_id === $request->user()->id, 403);
        $venue = $task->venue;

        foreach ($task->assets as $asset) {
            if (! $asset->isLink() && $asset->path) {
                Storage::disk($asset->disk)->delete($asset->path);
            }
        }
        $task->delete();

        return redirect()->route('board', $venue)->with('ok', 'დავალება წაიშალა.');
    }

    public function comment(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $task->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return back();
    }

    /**
     * Hand in work: either a file, or a link to the finished product
     * (Google Drive, Figma, Canva, Dropbox…). Optionally for one platform.
     */
    public function upload(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required_without:url', 'nullable', 'file', 'max:102400', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,application/pdf'],
            'url' => ['required_without:file', 'nullable', 'url:https,http', 'max:2048'],
            'task_channel_id' => ['nullable', Rule::exists('task_channels', 'id')->where('task_id', $task->id)],
        ], [
            'file.required_without' => 'ატვირთე ფაილი ან ჩასვი მზა პროდუქტის ბმული.',
            'url.required_without' => 'ატვირთე ფაილი ან ჩასვი მზა პროდუქტის ბმული.',
            'url.url' => 'ბმული არასწორია (უნდა იწყებოდეს https://-ით).',
        ]);

        $version = (int) $task->assets()->max('version') + 1;
        $common = [
            'user_id' => $request->user()->id,
            'task_channel_id' => $data['task_channel_id'] ?? null,
            'version' => $version,
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $disk = config('studio.assets_disk');

            $task->assets()->create([
                ...$common,
                'kind' => 'file',
                'disk' => $disk,
                'path' => $file->store("tasks/{$task->id}", $disk),
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        } else {
            $task->assets()->create([...$common, 'kind' => 'link', 'url' => $data['url']]);
        }

        $task->log($request->user(), 'asset', ['version' => $version]);

        $task->load('assignments');
        $mine = $task->assignmentFor($request->user());

        if ($mine && $request->boolean('mark_ready')) {
            // the uploader is on the task: their part is done
            $task->markReady($request->user());
        } elseif ($task->assignments->isEmpty() && in_array($task->status, ['todo', 'in_progress', 'revision'], true)) {
            // nobody assigned: fresh work goes straight to review
            $this->changeStatus($request->user(), $task, 'review');
        }

        $task->refresh();

        return back()->with('ok', $task->status === 'review'
            ? "v{$version} დაემატა და დავალება გადავიდა „დასადასტურებელში“."
            : "v{$version} დაემატა.");
    }

    public function asset(TaskAsset $asset): StreamedResponse
    {
        abort_if($asset->isLink() || ! $asset->path, 404);
        $storage = Storage::disk($asset->disk);
        abort_unless($storage->exists($asset->path), 404);

        return $storage->response($asset->path, $asset->original_name, [
            'Content-Type' => $asset->mime ?? 'application/octet-stream',
        ], request()->boolean('download') ? 'attachment' : 'inline');
    }

    private function changeStatus(User $user, Task $task, string $status): void
    {
        abort_unless(in_array($status, $task->allowedStatusesFor($user), true), 403, 'ამ სტატუსის დაყენება შენ როლს არ შეუძლია.');

        if ($task->status === $status) {
            return;
        }

        $from = $task->status;
        $task->status = $status;

        if ($status === 'approved') {
            $task->approved_by = $user->id;
            $task->approved_at = now();
        }

        $task->save();

        if ($status === 'revision') {
            // everyone's part goes back to their own space as "in progress"
            $task->reopenAssignments();
        }

        if ($status === 'published') {
            // marked published in one go: confirm every platform not yet confirmed
            $task->channels()->whereNull('published_at')->update(['published_at' => now(), 'published_by' => $user->id]);
        } elseif ($from === 'published') {
            $task->channels()->update(['published_at' => null, 'published_by' => null]);
        }

        $task->log($user, match ($status) {
            'approved' => 'approved',
            'revision' => 'revision',
            default => 'status',
        }, ['from' => $from, 'to' => $status]);
    }
}
