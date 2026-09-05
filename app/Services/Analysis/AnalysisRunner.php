<?php

namespace App\Services\Analysis;

use App\Models\Analysis;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 분석 실행 · 상태 전이를 한 곳에서 관리한다.
 * 컨트롤러(동기)와 큐 잡(비동기)이 모두 이 클래스를 호출한다.
 */
class AnalysisRunner
{
    public function __construct(private readonly MarketAnalyzer $analyzer) {}

    public function run(Analysis $analysis): Analysis
    {
        $analysis->update(['status' => 'processing', 'error_message' => null]);

        try {
            $payload = $this->analyzer->analyze($analysis);

            $analysis->update([
                'status' => 'completed',
                'payload' => $payload,
                'region_codes' => array_column($payload['meta']['regions'], 'code'),
                'address' => $analysis->address ?: ($payload['meta']['regions'][0]['name'] ?? null),
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('상권분석 실패', ['analysis' => $analysis->uuid, 'error' => $e->getMessage()]);

            $analysis->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $analysis->refresh();
    }
}
