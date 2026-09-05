<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Services\Analysis\RegionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function __construct(private readonly RegionResolver $resolver) {}

    /** 행정동 자동완성 */
    public function search(Request $request): JsonResponse
    {
        $regions = Region::search($request->query('q'))
            ->orderBy('full_name')
            ->limit(20)
            ->get(['code', 'full_name', 'sido_name', 'sigungu_name', 'dong_name', 'lat', 'lng']);

        return response()->json(['data' => $regions]);
    }

    /** 시군구 목록 (시도 선택 시) */
    public function sigungu(Request $request): JsonResponse
    {
        $names = Region::where('sido_name', $request->query('sido'))
            ->distinct()
            ->orderBy('sigungu_name')
            ->pluck('sigungu_name');

        return response()->json(['data' => $names]);
    }

    /** 행정동 목록 (시군구 선택 시) */
    public function dongs(Request $request): JsonResponse
    {
        $regions = Region::where('sido_name', $request->query('sido'))
            ->where('sigungu_name', $request->query('sigungu'))
            ->orderBy('dong_name')
            ->get(['code', 'dong_name', 'full_name', 'lat', 'lng']);

        return response()->json(['data' => $regions]);
    }

    /**
     * 반경 안에 걸리는 행정동과 겹침 비율 미리보기.
     * 분석을 실행하기 전에 "이 범위면 어떤 동이 잡히는지" 보여준다.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'min:100', 'max:5000'],
        ]);

        $resolved = $this->resolver->fromRadius(
            (float) $validated['lat'],
            (float) $validated['lng'],
            (int) $validated['radius_m']
        );

        return response()->json([
            'data' => $resolved->map(fn (array $item) => [
                'code' => $item['region']->code,
                'name' => $item['region']->full_name,
                'weight' => $item['weight'],
                'weight_percent' => round($item['weight'] * 100),
                'distance_km' => $item['distance_km'],
            ])->values(),
        ]);
    }
}
