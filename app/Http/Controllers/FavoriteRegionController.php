<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteRegionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'region_code' => ['required', 'exists:regions,code'],
        ]);

        $region = Region::where('code', $validated['region_code'])->firstOrFail();

        $request->user()->favoriteRegions()->firstOrCreate(
            ['region_code' => $region->code],
            ['label' => $region->full_name]
        );

        return back()->with('status', '관심지역에 추가했습니다.');
    }

    public function destroy(Request $request, string $regionCode): RedirectResponse
    {
        $request->user()->favoriteRegions()->where('region_code', $regionCode)->delete();

        return back()->with('status', '관심지역에서 제거했습니다.');
    }
}
