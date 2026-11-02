<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\ReportAction;
use App\Models\Venue;
use App\Reporting\AnalysisMarkdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Written analyses per brand: upload a Markdown/text report, read it, tick off its checklist. */
class AnalysisController extends Controller
{
    public function index(Request $request): View
    {
        $venues = Venue::orderBy('sort')->get();
        $brand = $venues->firstWhere('slug', $request->query('brand'));

        return view('reports.analyses.index', [
            'venues' => $venues,
            'brand' => $brand,
            'reports' => AnalysisReport::with('venue')
                ->withCount(['actions', 'actions as done_count' => fn ($q) => $q->whereNotNull('done_at')])
                ->when($brand, fn ($q) => $q->where('venue_id', $brand->id))
                ->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('reports.analyses.form', ['venues' => Venue::orderBy('sort')->get(), 'report' => new AnalysisReport]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $parsed = AnalysisMarkdown::split($data['body']);

        $report = DB::transaction(function () use ($data, $parsed, $request) {
            $report = AnalysisReport::create([
                'venue_id' => $data['venue_id'] ?? null,
                'user_id' => $request->user()->id,
                'slug' => AnalysisReport::uniqueSlug($data['title']),
                'title' => $data['title'],
                'period_from' => $data['period_from'] ?? null,
                'period_to' => $data['period_to'] ?? null,
                'summary' => $parsed['summary'] ?: null,
                'body' => $parsed['body'],
            ]);
            $this->createActions($report, $parsed['actions'], $request->user()->id);

            return $report;
        });

        return redirect()->route('reports.analyses.show', $report)
            ->with('ok', 'ანალიზი შენახულია'.($parsed['actions'] ? ' — ჩეკლისტში '.count($parsed['actions']).' პუნქტია.' : '.'));
    }

    public function show(AnalysisReport $analysis): View
    {
        $analysis->load(['venue', 'user', 'actions.doneBy']);

        return view('reports.analyses.show', [
            'report' => $analysis,
            'groups' => $analysis->actions->groupBy('group'),
            'venues' => Venue::orderBy('sort')->get(),
        ]);
    }

    public function update(Request $request, AnalysisReport $analysis): RedirectResponse
    {
        $data = $this->validated($request, false);
        $analysis->update($data);

        return back()->with('ok', 'შენახულია.');
    }

    public function destroy(AnalysisReport $analysis): RedirectResponse
    {
        $analysis->delete();

        return redirect()->route('reports.analyses.index')->with('ok', "„{$analysis->title}“ წაიშალა.");
    }

    public function toggle(Request $request, AnalysisReport $analysis, ReportAction $action): RedirectResponse
    {
        abort_unless((int) $action->analysis_report_id === (int) $analysis->id, 404);

        $action->update($action->done_at
            ? ['done_at' => null, 'done_by' => null]
            : ['done_at' => now(), 'done_by' => $request->user()->id]);

        return back();
    }

    private function createActions(AnalysisReport $report, array $actions, int $userId): void
    {
        foreach ($actions as $i => $a) {
            $report->actions()->create([
                'group' => $a['group'],
                'text' => $a['text'],
                'sort' => $i,
                'done_at' => $a['done'] ? now() : null,
                'done_by' => $a['done'] ? $userId : null,
            ]);
        }
    }

    private function validated(Request $request, bool $creating): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'venue_id' => ['nullable', Rule::exists('venues', 'id')],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
        ];
        if ($creating) {
            $rules['file'] = ['nullable', 'file', 'max:2048', 'mimetypes:text/plain,text/markdown,text/x-markdown,application/octet-stream'];
            $rules['body'] = ['nullable', 'string', 'max:500000'];
        }

        $data = $request->validate($rules, [], ['file' => 'ფაილი', 'body' => 'ტექსტი']);

        if ($creating) {
            $body = $request->hasFile('file') ? (string) file_get_contents($request->file('file')->getRealPath()) : (string) ($data['body'] ?? '');
            if (! mb_check_encoding($body, 'UTF-8')) {
                back()->withErrors(['file' => 'ფაილი UTF-8 ტექსტი უნდა იყოს (.md ან .txt).'])->throwResponse();
            }
            if (trim($body) === '') {
                back()->withErrors(['body' => 'ატვირთე ფაილი ან ჩასვი ტექსტი.'])->withInput()->throwResponse();
            }
            $data['body'] = $body;
            unset($data['file']);
        }

        return $data;
    }
}
