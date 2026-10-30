<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use RefreshDatabase;

    private Venue $batumi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->batumi = Venue::where('slug', 'batumi')->firstOrFail();
    }

    private function task(array $attrs = []): Task
    {
        return $this->batumi->tasks()->create([
            'title' => 'ბოშური საღამო — ანონსი',
            'platform' => 'instagram',
            'status' => 'todo',
            ...$attrs,
        ]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('სახლში Studio');
    }

    public function test_seeded_admin_can_log_in_and_sees_board(): void
    {
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'change-me-now'])
            ->assertRedirect(route('home'));

        $this->get('/')->assertRedirect(route('board', $this->batumi));
        $this->get(route('board', $this->batumi))->assertOk()->assertSee('ბათუმის სახლში')->assertSee('Instagram');
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'off@test.ge', 'is_active' => false]);

        $this->post('/login', ['email' => 'off@test.ge', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_creating_a_task_and_filtering_by_platform(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tasks.store', $this->batumi), [
            'title' => 'ია თომაშის საღამო',
            'platform' => 'facebook',
        ])->assertRedirect(route('board', $this->batumi));

        $this->assertDatabaseHas('tasks', ['title' => 'ია თომაშის საღამო', 'creator_id' => $user->id, 'status' => 'todo']);

        $this->get(route('board', [$this->batumi, 'platform' => 'story']))->assertOk()->assertDontSee('ია თომაშის საღამო');
        $this->get(route('board', [$this->batumi, 'platform' => 'facebook']))->assertOk()->assertSee('ია თომაშის საღამო');
    }

    public function test_designer_cannot_approve_but_manager_can(): void
    {
        $task = $this->task(['status' => 'review']);
        $designer = User::factory()->role('designer')->create();
        $manager = User::factory()->role('manager')->create();

        $this->actingAs($designer)->post(route('tasks.approve', $task))->assertForbidden();
        $this->actingAs($designer)->patch(route('tasks.quick', $task), ['status' => 'approved'])->assertForbidden();

        $this->actingAs($manager)->post(route('tasks.approve', $task))->assertRedirect();
        $task->refresh();
        $this->assertSame('approved', $task->status);
        $this->assertSame($manager->id, $task->approved_by);
    }

    public function test_upload_creates_version_and_sends_to_review(): void
    {
        Storage::fake('local');
        $task = $this->task(['status' => 'revision']);
        $designer = User::factory()->role('designer')->create();

        $this->actingAs($designer)->post(route('tasks.upload', $task), [
            'file' => UploadedFile::fake()->image('poster.png', 1080, 1350),
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('review', $task->status);
        $this->assertSame(1, $task->assets()->first()->version);
        Storage::disk('local')->assertExists($task->assets()->first()->path);
    }

    public function test_calendar_team_and_contacts_pages_render(): void
    {
        $this->task(['publish_at' => now()->startOfWeek()->addDay()->setTime(19, 30)]);
        $admin = User::where('role', 'admin')->first();

        $this->actingAs($admin)->get(route('calendar', $this->batumi))->assertOk()->assertSee('ბოშური საღამო — ანონსი');
        $this->get(route('team'))->assertOk()->assertSee('ვინ რაზე მუშაობს');
        $this->get(route('contacts'))->assertOk();
        $this->post(route('contacts.store'), ['name' => 'გიორგი ფრუიძე', 'type' => 'artist', 'venue_id' => $this->batumi->id])->assertRedirect();
        $this->assertDatabaseHas('contacts', ['name' => 'გიორგი ფრუიძე']);
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        $admin = User::where('role', 'admin')->first();

        $this->actingAs($admin)->patch(route('team.users.update', $admin), ['role' => 'smm', 'is_active' => 1])
            ->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
    }
}
