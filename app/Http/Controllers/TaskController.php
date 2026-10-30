<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAsset;
use App\Models\User;
use App\Models\Venue;
use App\Support\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'platform' => ['required', Rule::in(array_keys(Studio::PLATFORMS))],
            'format' => ['nullable', 'string', 'max:60'],
            'brief' => ['nullable', 'string', 'max:5000'],
            'assignee_id' => ['nullable', Rule::exists('users', 'id')],
            'due_on' => ['nullable', 'date'],
            'publish_at' => ['nullable', 'date'],
        ];
    }

    public function store(Request $request, Venue $venue): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $task = $venue->tasks()->create([
            ...$data,
            'status' => 'todo',
            'creator_id' => $request->user()->id,
        ]);
        $task->log($request->user(), 'created');

        return redirect()->route('board', $venue)->with('ok', 'დავალება დაემატა.');
    }

    public function show(Task $task): View
    {
        $task->load(['venue', 'assignee', 'creator', 'approver', 'assets.user', 'comments.user', 'activities.user']);

        return view('tasks.show', [
            'task' => $task,
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'allowed' => $task->allowedStatusesFor(request()->user()),
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $oldAssignee = $task->assignee_id;

        $task->update($data);
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
            Storage::disk($asset->disk)->delete($asset->path);
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

    public function upload(Request $request, Task $task): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:102400', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,application/pdf'],
        ]);

        $file = $request->file('file');
        $disk = config('studio.assets_disk');
        $version = (int) $task->assets()->max('version') + 1;
        $path = $file->store("tasks/{$task->id}", $disk);

        $task->assets()->create([
            'user_id' => $request->user()->id,
            'version' => $version,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
        $task->log($request->user(), 'asset', ['version' => $version]);

        // A fresh visual on a task that was sent back goes straight to review.
        if (in_array($task->status, ['todo', 'in_progress', 'revision'], true)) {
            $this->changeStatus($request->user(), $task, 'review');
        }

        return back()->with('ok', "ვიზუალი v{$version} აიტვირთა და გაიგზავნა დასადასტურებლად.");
    }

    public function asset(TaskAsset $asset): StreamedResponse
    {
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
