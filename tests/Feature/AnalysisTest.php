<?php

namespace Tests\Feature;

use App\Models\AnalysisReport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AnalysisTest extends TestCase
{
    use RefreshDatabase;

    private const MD = <<<'MD'
# ქუთაისი — რეკლამა და მიმოწერები

ქუთაისი ყველაზე იაფი მიმართულებაა.

## რეკლამა

| კამპანია | ხარჯი ($) | საუბარი |
|---|---:|---:|
| ტესტ კამპანია | 122.72 | 273 |

Suspicious: `bad-site[.]xyz` <script>alert(1)</script> [x](javascript:alert(1))

## სამოქმედო ჩეკლისტი

### დღეს

- [ ] ბალანსის გადახდა
- [x] ნომრის შემოწმება

### 2 კვირაში

- [ ] Pixel-ზე Lead ივენთი
MD;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function upload(array $extra = []): AnalysisReport
    {
        $kutaisi = Venue::where('slug', 'kutaisi')->firstOrFail();
        $this->actingAs($this->admin())->post(route('reports.analyses.store'), [
            'title' => 'ქუთაისი — რეკლამა',
            'venue_id' => $kutaisi->id,
            'period_from' => '2026-08-24',
            'period_to' => '2026-11-01',
            'file' => UploadedFile::fake()->createWithContent('kutaisi.md', self::MD),
            ...$extra,
        ])->assertRedirect();

        return AnalysisReport::latest('id')->firstOrFail();
    }

    public function test_upload_builds_body_and_checklist(): void
    {
        $report = $this->upload();

        $this->assertSame('ქუთაისი ყველაზე იაფი მიმართულებაა.', $report->summary);
        $this->assertCount(3, $report->actions);
        $this->assertSame(['დღეს', 'დღეს', '2 კვირაში'], $report->actions->pluck('group')->all());
        $this->assertNotNull($report->actions[1]->done_at);
        $this->assertStringNotContainsString('[ ]', $report->body);
        $this->assertStringNotContainsString('სამოქმედო ჩეკლისტი', $report->body);

        $this->get(route('reports.analyses.show', $report))
            ->assertOk()
            ->assertSee('<table>', false)
            ->assertSee('ტესტ კამპანია')
            ->assertSee('ბალანსის გადახდა')
            ->assertSee('1/3')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false);
    }

    public function test_checklist_items_can_be_ticked_and_unticked(): void
    {
        $report = $this->upload();
        $item = $report->actions->first();

        $this->post(route('reports.analyses.toggle', [$report, $item]))->assertRedirect();
        $this->assertNotNull($item->fresh()->done_at);
        $this->assertSame($this->admin()->id, $item->fresh()->done_by);

        $this->post(route('reports.analyses.toggle', [$report, $item]))->assertRedirect();
        $this->assertNull($item->fresh()->done_at);
    }

    public function test_pasted_text_works_and_empty_upload_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('reports.analyses.store'), ['title' => 'ცარიელი'])
            ->assertSessionHasErrors('body');

        $this->post(route('reports.analyses.store'), ['title' => 'ჩასმული', 'body' => "# ჩასმული\n\nტექსტი\n\n- [ ] ერთი"])
            ->assertRedirect();
        $this->assertSame(1, AnalysisReport::where('title', 'ჩასმული')->firstOrFail()->actions()->count());
    }

    public function test_listed_per_brand_and_on_dashboard(): void
    {
        $report = $this->upload();

        $this->get(route('reports.analyses.index', ['brand' => 'kutaisi']))->assertOk()->assertSee($report->title);
        $this->get(route('reports.analyses.index', ['brand' => 'batumi']))->assertOk()->assertDontSee($report->title);
        $this->get(route('reports.index', ['brand' => 'kutaisi']))->assertOk()->assertSee('ანალიზები და ჩეკლისტი')->assertSee($report->title);
    }

    public function test_edit_and_delete(): void
    {
        $report = $this->upload();
        $batumi = Venue::where('slug', 'batumi')->firstOrFail();

        $this->put(route('reports.analyses.update', $report), ['title' => 'ახალი სათაური', 'venue_id' => $batumi->id])->assertRedirect();
        $this->assertSame($batumi->id, $report->fresh()->venue_id);

        $this->delete(route('reports.analyses.destroy', $report))->assertRedirect(route('reports.analyses.index'));
        $this->assertSame(0, AnalysisReport::count());
        $this->assertDatabaseCount('report_actions', 0);
    }

    public function test_team_members_cannot_see_analyses(): void
    {
        $report = $this->upload();

        foreach (['smm', 'designer'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('reports.analyses.index'))->assertForbidden();
            $this->actingAs($user)->get(route('reports.analyses.show', $report))->assertForbidden();
        }
    }
}
