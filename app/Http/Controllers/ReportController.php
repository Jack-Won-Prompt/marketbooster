<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** 업로드 예시와 같은 구성의 PDF 리포트를 만든다. */
    public function pdf(Request $request, Analysis $analysis): Response|StreamedResponse
    {
        abort_unless($analysis->user_id === $request->user()->id, 403);
        abort_unless($analysis->isCompleted(), 404, '완료된 분석만 PDF 로 내려받을 수 있습니다.');

        // 한글 글꼴 서브셋 처리에 메모리를 많이 쓴다.
        ini_set('memory_limit', '512M');

        $pdf = Pdf::loadView('reports.pdf', [
            'analysis' => $analysis,
            'report' => $analysis->payload,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            '[MarketScope] %s_상권분석보고서_%s.pdf',
            preg_replace('/[\\\\\\/:*?"<>|]/', '', $analysis->title),
            now()->format('Ymd')
        );

        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    /**
     * 분석 범위 안의 프랜차이즈 브랜드 목록을 CSV 로 내려보낸다.
     * 브랜드명을 그대로 담아 창업 검토·경쟁 브랜드 확인에 쓸 수 있게 한다.
     */
    public function franchises(Request $request, Analysis $analysis): StreamedResponse
    {
        abort_unless($analysis->user_id === $request->user()->id, 403);
        abort_unless($analysis->isCompleted(), 404, '완료된 분석만 내려받을 수 있습니다.');

        $stores = $analysis->payload['stores'] ?? [];
        $brands = $stores['brands'] ?? [];

        $filename = sprintf(
            '[MarketScope] %s_프랜차이즈목록_%s.csv',
            preg_replace('/[\\\\\\/:*?"<>|]/', '', $analysis->title),
            now()->format('Ymd')
        );

        return response()->streamDownload(function () use ($analysis, $stores, $brands) {
            $out = fopen('php://output', 'w');

            // 엑셀이 UTF-8 을 알아보도록 BOM 을 먼저 쓴다.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['분석', $analysis->title]);
            fputcsv($out, ['분석 범위', $analysis->rangeLabel()]);
            fputcsv($out, ['기준', $analysis->payload['meta']['base_label'] ?? '-']);
            fputcsv($out, ['전체 점포', $stores['total'] ?? 0]);
            fputcsv($out, ['프랜차이즈 점포', $stores['franchise_total'] ?? 0]);
            fputcsv($out, []);
            fputcsv($out, ['분야', '브랜드', '매장 수', '전체 대비 비중(%)']);

            foreach ($brands as $brand) {
                fputcsv($out, [
                    $brand['sector_name'] ?? '',
                    $brand['name'] ?? '',
                    $brand['count'] ?? 0,
                    $brand['share'] ?? 0,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
