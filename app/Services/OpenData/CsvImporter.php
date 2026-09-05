<?php

namespace App\Services\OpenData;

use App\Models\DataImportLog;
use RuntimeException;

/**
 * 공공데이터포털 "파일데이터"(CSV) 적재기.
 *
 * 포털 CSV 는 대부분 EUC-KR(CP949) 인코딩에 한글 헤더를 쓴다.
 * 헤더 별칭표(HEADER_ALIASES)로 내부 컬럼을 찾아내고, 없으면 원본 헤더명을 그대로 컬럼으로 본다.
 */
class CsvImporter
{
    /** 내부 컬럼 => 허용 헤더명 목록 (공백/대소문자 무시하고 비교) */
    public const HEADER_ALIASES = [
        'region_code' => ['행정동코드', '행정동_코드', '행정구역코드', 'adstrd_code', 'admi_cd', 'hdong_cd', 'region_code'],
        'base_ym' => ['기준연월', '기준_년월', '기준년월', '기준월', 'stdr_ym', 'base_ym', 'ta_ym'],
        'gender' => ['성별', '성별구분', 'sex_cd', 'gender'],
        'age_band' => ['연령대', '연령대구분', '연령', 'age_cd', 'age_band'],
        'day_type' => ['요일구분', '평일주말구분', '주중주말', 'day_tp', 'day_type'],
        'time_band' => ['시간대', '시간대구분', 'time_zone', 'time_band'],
        'population' => ['유동인구수', '인구수', '총인구수', '거주인구수', '직장인구수', 'popltn_cnt', 'population'],
        'housing_type' => ['주거유형', '주택유형', 'housing_type'],
        'households' => ['세대수', '가구수', 'hshld_co', 'households'],
        'industry_code' => ['업종코드', '서비스업종코드', 'upjong_cd', 'svc_induty_cd', 'industry_code'],
        'industry_name' => ['업종명', '서비스업종코드명', 'upjong_nm', 'industry_name'],
        'sales_amount' => ['매출금액', '매출액', '당월매출금액', 'selng_amt', 'sales_amount'],
        'sales_count' => ['매출건수', '당월매출건수', 'selng_co', 'sales_count'],
        'school_type' => ['학교급', '학교구분', '구분', 'school_type'],
        'student_count' => ['학생수', '학생_수', 'student_count'],
        'category' => ['학원구분', '분류', 'category'],
        'academy_count' => ['학원수', '학원_수', 'academy_count'],
        'complex_name' => ['단지명', '아파트명', 'complex_name'],
        'move_in_ym' => ['입주년월', '입주예정년월', 'move_in_ym'],
        'sido_code' => ['시도코드', 'sido_code'],
        'sido_name' => ['시도명', '시도', 'sido_name'],
        'sigungu_code' => ['시군구코드', 'sigungu_code'],
        'sigungu_name' => ['시군구명', '시군구', 'sigungu_name'],
        'dong_name' => ['행정동명', '읍면동명', '행정동', 'dong_name'],
        'full_name' => ['전체명칭', '주소', 'full_name'],
        'lat' => ['위도', 'lat', 'y'],
        'lng' => ['경도', 'lng', 'lon', 'x'],
        'area_km2' => ['면적', '면적_km2', 'area_km2'],
        'code' => ['행정동코드', '코드', 'code'],
    ];

    public function __construct(
        private readonly RecordNormalizer $normalizer,
        private readonly DatasetWriter $writer,
    ) {}

    /**
     * @param  callable(string): void|null  $progress
     * @return array{imported:int, skipped:int, rows:int}
     */
    public function import(string $type, string $path, ?string $baseYm = null, ?callable $progress = null): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("CSV 파일을 읽을 수 없습니다: {$path}");
        }

        $log = DataImportLog::start($type, 'csv', $baseYm, $path);

        try {
            $handle = fopen($path, 'r');
            $header = null;
            $buffer = [];
            $imported = 0;
            $skipped = 0;
            $rows = 0;

            while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($line === [null] || $line === []) {
                    continue;
                }

                $line = array_map(fn ($v) => $this->toUtf8((string) $v), $line);

                if ($header === null) {
                    $header = $this->resolveHeader($line);

                    continue;
                }

                $rows++;
                $record = $this->buildRecord($header, $line, $baseYm);
                $buffer[] = $record;

                if (count($buffer) >= 2000) {
                    $result = $this->writer->write($type, $buffer);
                    $imported += $result['imported'];
                    $skipped += $result['skipped'];
                    $buffer = [];
                    $progress && $progress(number_format($rows).'행 처리…');
                }
            }

            fclose($handle);

            if ($buffer !== []) {
                $result = $this->writer->write($type, $buffer);
                $imported += $result['imported'];
                $skipped += $result['skipped'];
            }

            $log->succeed($imported, $skipped);

            return ['imported' => $imported, 'skipped' => $skipped, 'rows' => $rows];
        } catch (\Throwable $e) {
            $log->fail($e->getMessage());

            throw $e;
        }
    }

    /** 헤더 각 열이 내부 컬럼 중 무엇인지 판정한다. */
    private function resolveHeader(array $line): array
    {
        $resolved = [];

        foreach ($line as $index => $raw) {
            $key = $this->slug($raw);
            $resolved[$index] = null;

            foreach (self::HEADER_ALIASES as $column => $aliases) {
                foreach ($aliases as $alias) {
                    if ($this->slug($alias) === $key) {
                        $resolved[$index] = $column;

                        break 2;
                    }
                }
            }

            // 별칭에 없으면 헤더명을 그대로 컬럼명으로 사용 (영문 스네이크 헤더 대응)
            $resolved[$index] ??= $key;
        }

        return $resolved;
    }

    private function buildRecord(array $header, array $line, ?string $baseYm): array
    {
        $record = [];

        foreach ($line as $index => $value) {
            $column = $header[$index] ?? null;

            if ($column) {
                $record[$column] = trim($value);
            }
        }

        // CSV 에 기준연월 열이 없으면 커맨드 인자로 받은 값을 채운다.
        if ($baseYm && blank($record['base_ym'] ?? null)) {
            $record['base_ym'] = $baseYm;
        }

        return $this->normalizer->normalizeCodes($record);
    }

    /** BOM 제거 + 공백/기호 제거 후 소문자 비교 키 생성 */
    private function slug(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = preg_replace('/[\s_\-\.\(\)\[\]]/u', '', $value);

        return mb_strtolower(trim($value));
    }

    /** CP949/EUC-KR CSV 를 UTF-8 로 변환한다. */
    private function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'CP949, EUC-KR, UTF-8');
    }
}
