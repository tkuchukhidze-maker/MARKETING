<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use App\Services\Insights;
use App\Support\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Insights $insights): View
    {
        $venues = Venue::orderBy('sort')->get();
        $open = Task::open()->get(['id', 'venue_id', 'assignee_id', 'status', 'due_on']);

        $team = User::orderByRaw('is_active desc')->orderBy('name')->get()->map(function (User $u) use ($open, $venues) {
            $mine = $open->where('assignee_id', $u->id);

            return [
                'user' => $u,
                'total' => $mine->count(),
                'review' => $mine->where('status', 'review')->count(),
                'overdue' => $mine->filter(fn ($t) => $t->due_on && $t->due_on->isBefore(today()))->count(),
                'byVenue' => $venues->mapWithKeys(fn ($v) => [$v->id => $mine->where('venue_id', $v->id)->count()]),
            ];
        });

        $venueStats = $venues->map(fn (Venue $v) => [
            'venue' => $v,
            'open' => $open->where('venue_id', $v->id)->count(),
            'review' => $open->where('venue_id', $v->id)->where('status', 'review')->count(),
            'week' => collect(Studio::PLATFORMS)->map(fn ($d, $k) => $v->tasks()
                ->where('platform', $k)
                ->whereBetween('publish_at', [today()->startOfWeek(), today()->endOfWeek()])
                ->count()),
        ]);

        return view('team', [
            'team' => $team,
            'venues' => $venues,
            'venueStats' => $venueStats,
            'hints' => $insights->for(),
            'threshold' => Insights::OVERLOAD_THRESHOLD,
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(Studio::ROLES))],
            'password' => ['required', Password::min(8)],
        ]);

        User::create([...$data, 'avatar_color' => Studio::AVATAR_COLORS[array_rand(Studio::AVATAR_COLORS)]]);

        return back()->with('ok', 'გუნდის წევრი დაემატა.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(Studio::ROLES))],
            'is_active' => ['required', 'boolean'],
        ]);

        // never lock the last admin out
        if ($user->isAdmin() && ($data['role'] !== 'admin' || ! $data['is_active'])
            && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['role' => 'ბოლო ადმინისტრატორს ვერ შეცვლი.']);
        }

        $user->update($data);

        return back()->with('ok', 'შენახულია.');
    }

    public function contacts(Request $request): View
    {
        $type = $request->query('type');
        $type = array_key_exists((string) $type, Studio::CONTACT_TYPES) ? $type : null;

        return view('contacts', [
            'contacts' => Contact::with('venue')->when($type, fn ($q) => $q->where('type', $type))->orderBy('name')->get(),
            'venues' => Venue::orderBy('sort')->get(),
            'type' => $type,
        ]);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        Contact::create($this->validateContact($request));

        return back()->with('ok', 'კონტაქტი დაემატა.');
    }

    public function updateContact(Request $request, Contact $contact): RedirectResponse
    {
        $contact->update($this->validateContact($request));

        return back()->with('ok', 'შენახულია.');
    }

    public function destroyContact(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return back()->with('ok', 'კონტაქტი წაიშალა.');
    }

    private function validateContact(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Studio::CONTACT_TYPES))],
            'venue_id' => ['nullable', Rule::exists('venues', 'id')],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'instagram' => ['nullable', 'string', 'max:190'],
            'next_step' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
