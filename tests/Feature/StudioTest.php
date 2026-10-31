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

    private function task(array $attrs = [], array $channels = ['instagram' => 'Reels']): Task
    {
        $task = $this->batumi->tasks()->create([
            'title' => 'ბოშური საღამო — ანონსი',
            'status' => 'todo',
            ...$attrs,
        ]);
        $task->syncChannels($channels);

        return $task;
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
            'platforms' => ['facebook', 'instagram'],
            'formats' => ['facebook' => 'პოსტერი', 'instagram' => 'პოსტი 4:5', 'story' => 'ignored'],
            'reference_links' => "https://pinterest.com/pin/1\n\nhttps://www.behance.net/x",
            // assigned, so the "no assignee" reminder doesn't mention it on every filter
            'assignees' => [['user_id' => $user->id, 'note' => 'დიზაინი'], ['user_id' => '', 'note' => '']],
        ])->assertRedirect(route('board', $this->batumi));

        $task = Task::where('title', 'ია თომაშის საღამო')->firstOrFail();
        $this->assertSame($user->id, $task->creator_id);
        $this->assertSame(['facebook' => 'პოსტერი', 'instagram' => 'პოსტი 4:5'], $task->channels->pluck('format', 'platform')->all());
        $this->assertSame(['https://pinterest.com/pin/1', 'https://www.behance.net/x'], $task->reference_links);

        $this->get(route('board', [$this->batumi, 'platform' => 'story']))->assertOk()->assertDontSee('ია თომაშის საღამო');
        $this->get(route('board', [$this->batumi, 'platform' => 'instagram']))->assertOk()->assertSee('ია თომაშის საღამო');
        $this->get(route('board', [$this->batumi, 'platform' => 'facebook']))->assertOk()->assertSee('პოსტერი');
    }

    public function test_task_needs_a_platform_and_valid_reference_links(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tasks.store', $this->batumi), ['title' => 'x'])
            ->assertSessionHasErrors('platforms');
        $this->post(route('tasks.store', $this->batumi), ['title' => 'x', 'platforms' => ['story'], 'reference_links' => 'not a link'])
            ->assertSessionHasErrors('reference_links');
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_editing_platforms_keeps_the_ones_that_stay(): void
    {
        $task = $this->task([], ['facebook' => 'პოსტი', 'instagram' => 'Reels']);
        $keptId = $task->channels()->where('platform', 'instagram')->value('id');

        $this->actingAs(User::factory()->create())->put(route('tasks.update', $task), [
            'title' => $task->title,
            'platforms' => ['instagram', 'story'],
            'formats' => ['instagram' => 'კარუსელი', 'story' => 'სთორი 9:16'],
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame(['instagram', 'story'], $task->channels->pluck('platform')->all());
        $this->assertSame($keptId, $task->channels->firstWhere('platform', 'instagram')->id);
        $this->assertSame('კარუსელი', $task->channels->firstWhere('platform', 'instagram')->format);
    }

    public function test_finished_work_can_be_handed_in_as_a_link_for_one_platform(): void
    {
        $task = $this->task([], ['facebook' => 'პოსტერი', 'story' => 'სთორი 9:16']);
        $story = $task->channels()->where('platform', 'story')->first();

        $this->actingAs(User::factory()->role('designer')->create())->post(route('tasks.upload', $task), [
            'url' => 'https://drive.google.com/file/d/abc/view',
            'task_channel_id' => $story->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $asset = $task->assets()->first();
        $this->assertTrue($asset->isLink());
        $this->assertSame($story->id, $asset->task_channel_id);
        $this->assertSame('review', $task->fresh()->status);

        $this->get(route('tasks.show', $task))->assertOk()->assertSee('drive.google.com');
        $this->get(route('assets.show', $asset))->assertNotFound();
    }

    public function test_hand_in_rejects_a_foreign_platform_and_empty_submissions(): void
    {
        $task = $this->task();
        $other = $this->task(['title' => 'სხვა'], ['facebook' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tasks.upload', $task), [])->assertSessionHasErrors(['file', 'url']);
        $this->post(route('tasks.upload', $task), [
            'url' => 'https://figma.com/x',
            'task_channel_id' => $other->channels()->first()->id,
        ])->assertSessionHasErrors('task_channel_id');
    }

    public function test_one_task_for_three_people_each_with_their_own_part(): void
    {
        $manager = User::factory()->role('manager')->create();
        $designer = User::factory()->role('designer')->create();
        $photographer = User::factory()->role('photographer')->create();
        $smm = User::factory()->role('smm')->create();

        $this->actingAs($manager)->post(route('tasks.store', $this->batumi), [
            'title' => 'ია თომაშის საღამო',
            'platforms' => ['facebook', 'story'],
            'assignees' => [
                ['user_id' => $designer->id, 'note' => 'პოსტერი'],
                ['user_id' => $photographer->id, 'note' => 'ფოტოები'],
                ['user_id' => $smm->id, 'note' => 'ტექსტი'],
            ],
        ])->assertRedirect();

        $task = Task::where('title', 'ია თომაშის საღამო')->firstOrFail();
        $this->assertSame(3, $task->assignments()->count());

        // everyone sees only their own part in their own space
        $this->actingAs($designer)->get('/')->assertRedirect(route('workspace'));
        $this->get(route('workspace'))->assertOk()->assertSee('ია თომაშის საღამო')
            ->assertSee('<b>შენი ნაწილი:</b> პოსტერი', false)
            ->assertDontSee('<b>შენი ნაწილი:</b> ფოტოები', false);
        $this->actingAs($photographer)->get(route('workspace'))->assertOk()->assertSee('<b>შენი ნაწილი:</b> ფოტოები', false);

        // start → in progress on the board
        $this->actingAs($designer)->post(route('tasks.start', $task))->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertSame('in_progress', $task->assignmentFor($designer)->fresh()->status);

        // two ready, one still working → stays in progress
        $this->post(route('tasks.ready', $task));
        $this->actingAs($photographer)->post(route('tasks.ready', $task));
        $this->assertSame('in_progress', $task->fresh()->status);

        // last one ready → goes to review on the board
        $this->actingAs($smm)->post(route('tasks.ready', $task));
        $this->assertSame('review', $task->fresh()->status);

        // sent back: everyone's part returns to "in progress" in their space
        $this->actingAs($manager)->post(route('tasks.revision', $task), ['body' => 'თარიღი უფრო დიდად'])->assertRedirect();
        $task->refresh();
        $this->assertSame('revision', $task->status);
        $this->assertSame(['in_progress'], $task->assignments()->pluck('status')->unique()->values()->all());
    }

    public function test_at_most_three_people_and_only_they_can_start_or_finish(): void
    {
        $manager = User::factory()->role('manager')->create();
        $people = User::factory()->count(4)->create();

        $this->actingAs($manager)->post(route('tasks.store', $this->batumi), [
            'title' => 'ოთხი',
            'platforms' => ['story'],
            'assignees' => $people->map(fn ($u) => ['user_id' => $u->id])->all(),
        ])->assertSessionHasErrors('assignees');

        $task = $this->task();
        $this->actingAs($people[0])->post(route('tasks.start', $task))->assertForbidden();
        $this->post(route('tasks.ready', $task))->assertForbidden();
    }

    public function test_uploading_as_a_person_on_the_task_marks_their_part_ready(): void
    {
        $designer = User::factory()->role('designer')->create();
        $writer = User::factory()->role('copywriter')->create();
        $task = $this->task(['status' => 'in_progress']);
        $task->syncAssignments([$designer->id => 'დიზაინი', $writer->id => 'ტექსტი']);

        $this->actingAs($designer)->post(route('tasks.upload', $task), [
            'url' => 'https://www.figma.com/file/abc',
            'mark_ready' => 1,
        ])->assertRedirect();

        $task->refresh()->load('assignments');
        $this->assertSame('ready', $task->assignmentFor($designer)->status);
        $this->assertSame('in_progress', $task->status, 'still waiting for the copywriter');

        $this->get(route('board', $this->batumi))->assertOk()->assertSee('1/2 მზადაა');
    }

    public function test_admin_and_social_media_manager_approve_and_confirm_publishing_per_platform(): void
    {
        $smm = User::factory()->role('smm')->create();
        $designer = User::factory()->role('designer')->create();
        $task = $this->task(['status' => 'review', 'publish_at' => now()->subHour()], ['facebook' => 'პოსტერი', 'story' => 'სთორი 9:16']);
        $fb = $task->channels()->where('platform', 'facebook')->first();
        $story = $task->channels()->where('platform', 'story')->first();

        // the designer can neither approve nor confirm publishing
        $this->actingAs($designer)->post(route('tasks.approve', $task))->assertForbidden();
        $this->post(route('tasks.publish', [$task, $fb]))->assertForbidden();

        // the social media manager approves
        $this->actingAs($smm)->post(route('tasks.approve', $task))->assertRedirect();
        $this->assertSame('approved', $task->fresh()->status);

        // the board reminds that it's past time and not confirmed live
        $this->get(route('board', $this->batumi))->assertSee('დაადასტურე: Facebook, Story');

        // confirm Facebook with the post link: not finished until Story too
        $this->post(route('tasks.publish', [$task, $fb]), ['post_url' => 'https://facebook.com/batumissakhlshi/posts/1'])->assertRedirect();
        $this->assertSame('approved', $task->fresh()->status);
        $this->assertSame('https://facebook.com/batumissakhlshi/posts/1', $fb->fresh()->post_url);
        $this->assertSame($smm->id, $fb->fresh()->published_by);

        // the admin confirms Story → the task is published
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin)->post(route('tasks.publish', [$task, $story]))->assertRedirect();
        $this->assertSame('published', $task->fresh()->status);
        $this->get(route('tasks.show', $task))->assertOk()->assertSee('✓ გამოქვეყნდა');

        // undo one → back to approved
        $this->delete(route('tasks.unpublish', [$task, $story]))->assertRedirect();
        $this->assertSame('approved', $task->fresh()->status);
    }

    public function test_designer_workspace_has_everything_on_the_card(): void
    {
        $designer = User::factory()->role('designer')->create();
        $task = $this->task([
            'status' => 'in_progress',
            'brief' => 'ბოშური საღამოს ანონსი, თარიღი დიდად',
            'reference_links' => ['https://pinterest.com/pin/9'],
        ], ['instagram' => 'პოსტი 4:5', 'story' => 'სთორი 9:16']);
        $task->syncAssignments([$designer->id => 'პოსტერი']);
        $task->startWork($designer);

        $this->actingAs($designer)->get(route('workspace'))->assertOk()
            ->assertSee('ახლა ვმუშაობ')
            ->assertSee('ბოშური საღამოს ანონსი, თარიღი დიდად')
            ->assertSee('1080×1350 px')
            ->assertSee('1080×1920 px')
            ->assertSee('pinterest.com')
            ->assertSee('ატვირთვა და მზადაა')
            ->assertDontSee('გუნდი და დატვირთვა');
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

    public function test_board_shows_the_whole_pipeline_with_what_to_post_and_what_went_live(): void
    {
        $smm = User::factory()->create(['role' => 'smm']);
        $designer = User::factory()->create(['role' => 'designer']);

        $this->task(['title' => 'კვირის აფიშა', 'status' => 'review']);
        $late = $this->task(['title' => 'ღვინის საღამო', 'status' => 'approved', 'publish_at' => now()->subHours(2)], ['facebook' => 'პოსტი', 'story' => 'სთორი 9:16']);
        $live = $this->task(['title' => 'ახალი მენიუ', 'status' => 'approved'], ['instagram' => 'პოსტი 4:5']);
        $live->confirmPublished($live->channels->first(), $smm, 'https://instagram.com/p/abc');

        // the social media manager sees the stages, can approve and confirm per platform right on the card
        $this->actingAs($smm)->get(route('board', $this->batumi))->assertOk()
            ->assertSeeInOrder(['გასაკეთებელი', 'მუშავდება', 'დასადასტურებელი', 'განსათავსებელი', 'განთავსდა'])
            ->assertSee('კვირის აფიშა')
            ->assertSee(route('tasks.approve', Task::where('title', 'კვირის აფიშა')->first()))
            ->assertSee('დაგვიანდა')
            ->assertSee(route('tasks.publish', [$late, $late->channels->firstWhere('platform', 'facebook')]))
            ->assertSee('https://instagram.com/p/abc')
            ->assertSee('დადო: '.$smm->name, false);

        // everyone sees the same board, but only managers get the buttons
        $this->actingAs($designer)->get(route('board', $this->batumi))->assertOk()
            ->assertSee('ღვინის საღამო')->assertSee('ახალი მენიუ')
            ->assertDontSee(route('tasks.publish', [$late, $late->channels->first()]))
            ->assertDontSee(route('tasks.approve', Task::where('title', 'კვირის აფიშა')->first()));

        // the old table is still one click away
        $this->actingAs($smm)->get(route('board', [$this->batumi, 'view' => 'list']))->assertOk()
            ->assertSee('ღვინის საღამო')->assertDontSee('kanban');
    }
}
