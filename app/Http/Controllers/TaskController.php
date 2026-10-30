<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAsset;
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
     * @return array{0: array<string, mixed>, 1: array<string, ?string>}
     */
    private function validateTask(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['distinct', Rule::in(array_keys(Studio::PLATFORMS))],
            'formats' => ['nullable', 'array'],
            'formats.*' => ['nullable', 'string', 'max:60'],
            'brief' => ['nullable', 'string', 'max:5000'],
            'reference_links' => ['nullable', 'string', 'max:5000'],
            'assignee_id' => ['nullable', Rule::exists('users', 'id')],
            'due_on' => ['nullable', 'date'],
            'publish_at' => ['nullable', 'date'],
        ], [
            'platforms.required' => 'აირჩიე მინიმუმ ერთი სოციალური ქსელი.',
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

        $fields = collect($data)->only(['title', 'brief', 'assignee_id', 'due_on', 'publish_at'])->all();
        $fields['reference_links'] = $links->isEmpty() ? null : $links->all();

        return [$fields, $channels];
    }

    public function store(Request $request, Venue $venue): RedirectResponse
    {
        [$fields, $channels] = $this->validateTask($request);

        $task = DB::transaction(function () use ($venue, $fields, $channels, $request) {
            $task = $venue->tasks()->create([
                ...$fields,
                'status' => 'todo',
                'creator_id' => $request->user()->id,
            ]);
            $task->syncChannels($channels);

            return $task;
        });
        $task->log($request->user(), 'created');

        return redirect()->route('board', $venue)->with('ok', 'დავალება დაემატა.');
    }

    public function show(Task $task): View
    {
        $task->load(['venue', 'channels', 'assignee', 'creator', 'approver', 'assets.user', 'assets.channel', 'comments.user', 'activities.user']);

        return view('tasks.show', [
            'task' => $task,
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'allowed' => $task->allowedStatusesFor(request()->user()),
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        [$fields, $channels] = $this->validateTask($request);
        $oldAssignee = $task->assignee_id;

        DB::transaction(function () use ($task, $fields, $channels) {
            $task->update($fields);
            $task->syncChannels($channels);
        });
        $task->log($request->user(), 'updated');

        if ($task->assignee_id && (int) $task->assignee_id !== (int) $oldAssignee) {
            $task->log($request->user(), 'assigned', ['to' => $task->assignee?->name]);
        }

        return back()->with('ok', 'შენახულია.');
    }

    /** Quick inline change from the board: status or assignee. */
    public function quick(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(Studio::STATUSES))],
            'assignee_id' => ['sometimes', 'nullable', Rule::exists('users', 'id')],
        ]);

        if (array_key_exists('status', $data)) {
            $this->changeStatus($request->user(), $task, $data['status']);
        }

        if (array_key_exists('assignee_id', $data) && $data['assignee_id'] != $task->assignee_id) {
            $task->update(['assignee_id' => $data['assignee_id']]);
            $task->log($request->user(), 'assigned', ['to' => $task->fresh()->assignee?->name]);
        }

        return back();
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

        // Fresh work on a task that was open or sent back goes straight to review.
        if (in_array($task->status, ['todo', 'in_progress', 'revision'], true)) {
            $this->changeStatus($request->user(), $task, 'review');
        }

        return back()->with('ok', "v{$version} დაემატა და გაიგზავნა დასადასტურებლად.");
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
        $task->log($user, match ($status) {
            'approved' => 'approved',
            'revision' => 'revision',
            default => 'status',
        }, ['from' => $from, 'to' => $status]);
    }
}
