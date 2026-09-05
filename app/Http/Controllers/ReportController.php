<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Services\Reports\StaticMapRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly StaticMapRenderer $maps) {}

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
            // dompdf 는 JavaScript 를 못 돌리므로 지도는 서버에서 그려 그림으로 넣는다.
            'mapImage' => $this->mapDataUri($analysis),
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
     * 웹 리포트 화면에 넣을 지도 그림.
     * HTML 에 data URI 를 그대로 박으면 페이지가 수백 KB 무거워지므로 따로 내보낸다.
     */
    public function map(Request $request, Analysis $analysis): Response
    {
        abort_unless($analysis->user_id === $request->user()->id, 403);
        abort_unless($analysis->isCompleted(), 404);

        $png = $this->maps->render(
            $analysis->payload['meta'] ?? [],
            (int) config('map.static_width', 900),
            (int) config('map.static_height', 560),
        );

        abort_if($png === null, 404, '이 분석에는 그릴 지도가 없습니다.');

        return response($png, 200, [
            'Content-Type' => 'image/png',
            // 분석 payload 는 재분석하지 않는 한 바뀌지 않는다.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** PDF 에 넣을 지도. 타일 서버가 죽어도 리포트는 나와야 하므로 실패를 삼킨다. */
    private function mapDataUri(Analysis $analysis): ?string
    {
        try {
            return $this->maps->renderDataUri(
                $analysis->payload['meta'] ?? [],
                (int) config('map.static_width', 900),
                (int) config('map.static_height', 560),
            );
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
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
            fputcsv($out, ['다점포 상호 점포', $stores['chain_total'] ?? 0]);
            fputcsv($out, []);
            fputcsv($out, ['분야', '브랜드', '구분', '매장 수', '전체 대비 비중(%)']);

            foreach ($brands as $brand) {
                fputcsv($out, [
                    $brand['sector_name'] ?? '',
                    $brand['name'] ?? '',
                    $brand['source_label'] ?? '프랜차이즈',
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
