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
}
