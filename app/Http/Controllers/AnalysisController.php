<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Region;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\StatisticsRepository;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnalysisController extends Controller
{
    public function __construct(
        private readonly AnalysisRunner $runner,
        private readonly StatisticsRepository $stats,
    ) {}

    public function index(Request $request): View
    {
        return view('analyses.index', [
            'analyses' => $request->user()->analyses()->paginate(12),
        ]);
    }

    public function create(Request $request): View
    {
        $periods = $this->stats->availablePeriods();

        return view('analyses.create', [
            'radiusOptions' => config('map.radius_options'),
            'defaultRadius' => config('map.default_radius'),
            'defaultCenter' => config('map.default_center'),
            'periods' => $periods,
            'defaultPeriod' => $periods[0] ?? Period::month(now()->subMonth()->format('Ym')),
            'sidoList' => Region::query()->distinct()->orderBy('sido_name')->pluck('sido_name'),
            'favorites' => $request->user()->favoriteRegions()->with('region')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'mode' => ['required', Rule::in(['radius', 'region'])],
            'center_lat' => ['required_if:mode,radius', 'nullable', 'numeric', 'between:-90,90'],
            'center_lng' => ['required_if:mode,radius', 'nullable', 'numeric', 'between:-180,180'],
            'radius_m' => ['required_if:mode,radius', 'nullable', 'integer', 'min:100', 'max:5000'],
            'address' => ['nullable', 'string', 'max:200'],
            'region_codes' => ['required_if:mode,region', 'nullable', 'array', 'max:30'],
            'region_codes.*' => ['string', 'exists:regions,code'],
            // 월(YYYYMM) 또는 분기(YYYYQ) 코드를 받는다.
            'period' => ['required', 'regex:/^(\d{6}|\d{4}[1-4])$/'],
        ], [
            'region_codes.required_if' => '분석할 행정동을 한 곳 이상 선택해 주세요.',
            'center_lat.required_if' => '지도를 클릭하거나 주소를 검색해 중심 지점을 지정해 주세요.',
            'period.regex' => '기준 기간은 YYYYMM(월) 또는 YYYYQ(분기) 형식이어야 합니다.',
        ]);

        $period = Period::parse($validated['period']);

        $analysis = $request->user()->analyses()->create([
            'title' => $validated['title'],
            'mode' => $validated['mode'],
            'center_lat' => $validated['center_lat'] ?? null,
            'center_lng' => $validated['center_lng'] ?? null,
            'radius_m' => $validated['radius_m'] ?? null,
            'address' => $validated['address'] ?? null,
            'region_codes' => $validated['region_codes'] ?? [],
            'status' => 'pending',
        ] + $period->columns());

        $this->runner->run($analysis);

        return redirect()->route('analyses.show', $analysis)
            ->with('status', '상권분석이 완료되었습니다.');
    }

    public function show(Request $request, Analysis $analysis): View
    {
        $this->authorizeOwner($request, $analysis);

        return view('analyses.show', [
            'analysis' => $analysis,
            'report' => $analysis->payload ?? [],
        ]);
    }

    public function rerun(Request $request, Analysis $analysis): RedirectResponse
    {
        $this->authorizeOwner($request, $analysis);

        $latest = $this->stats->latestPeriod();

        if ($latest) {
            $analysis->update($latest->columns());
        }

        $this->runner->run($analysis);

        return redirect()->route('analyses.show', $analysis)
            ->with('status', '최신 데이터로 다시 분석했습니다.');
    }

    public function destroy(Request $request, Analysis $analysis): RedirectResponse
    {
        $this->authorizeOwner($request, $analysis);
        $analysis->delete();

        return redirect()->route('analyses.index')->with('status', '분석을 삭제했습니다.');
    }

    private function authorizeOwner(Request $request, Analysis $analysis): void
    {
        abort_unless($analysis->user_id === $request->user()->id, 403);
    }
}
