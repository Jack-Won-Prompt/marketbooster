<?php

namespace App\Services\OpenData;

use App\Support\Taxonomy;

/**
 * 기관마다 다른 필드명 · 코드값을 내부 표준 스키마로 변환한다.
 * 매핑 규칙은 config/opendata.php 의 datasets.*.map / normalize 에 있다.
 */
class RecordNormalizer
{
    /**
     * @param  array<string, array<int, string>>  $map  내부컬럼 => 원본 필드 후보 목록
     * @return array<string, mixed>
     */
    public function apply(array $row, array $map): array
    {
        $normalized = [];

        foreach ($map as $column => $candidates) {
            $normalized[$column] = $this->pick($row, (array) $candidates);
        }

        return $this->normalizeCodes($normalized);
    }

    /**
     * 후보 필드명을 순서대로 찾아 첫 값을 쓴다.
     * 기관마다 admiCd / ADMI_CD / admicd 처럼 표기가 갈리므로
     * 대소문자와 구분자(_ - . 공백) 차이는 무시하고 비교한다.
     */
    private function pick(array $row, array $candidates): mixed
    {
        $flattened = [];

        foreach ($row as $key => $value) {
            $flattened[$this->fieldKey((string) $key)] = $value;
        }

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $row) && $row[$candidate] !== '') {
                return $row[$candidate];
            }

            $key = $this->fieldKey((string) $candidate);

            if (array_key_exists($key, $flattened) && $flattened[$key] !== '') {
                return $flattened[$key];
            }
        }

        return null;
    }

    private function fieldKey(string $name): string
    {
        return strtolower(preg_replace('/[\s_\-.]/u', '', $name));
    }

    /** 성별/요일/시간대/연령 코드를 내부 표준값으로 치환한다. */
    public function normalizeCodes(array $row): array
    {
        $dict = config('opendata.normalize');

        foreach (['gender', 'day_type', 'time_band', 'age_band', 'housing_type', 'school_type', 'category'] as $field) {
            if (! array_key_exists($field, $row) || $row[$field] === null) {
                continue;
            }

            $row[$field] = $this->normalizeOne($field, (string) $row[$field], $dict[$field] ?? []);
        }

        if (isset($row['base_ym'])) {
            $digits = preg_replace('/\D/', '', (string) $row['base_ym']);
            // 5자리면 분기 코드(YYYYQ)이므로 자르지 않는다.
            $row['base_ym'] = strlen($digits) === 5 ? $digits : substr($digits, 0, 6);
        }

        if (isset($row['base_yq'])) {
            $row['base_yq'] = substr(preg_replace('/\D/', '', (string) $row['base_yq']), 0, 5);
        }

        if (isset($row['region_code'])) {
            $row['region_code'] = preg_replace('/\D/', '', (string) $row['region_code']);
        }

        foreach (['population', 'sales_amount', 'sales_count', 'households', 'student_count', 'academy_count'] as $numeric) {
            if (! array_key_exists($numeric, $row)) {
                continue;
            }

            $value = $row[$numeric];

            // 지수 표기(3.6E9)를 기호부터 지우면 값이 망가지므로 숫자면 그대로 캐스팅한다.
            $row[$numeric] = is_numeric($value)
                ? (int) round((float) $value)
                : (int) round((float) preg_replace('/[^0-9.\-]/', '', (string) $value));
        }

        return $row;
    }

    private function normalizeOne(string $field, string $raw, array $dict): ?string
    {
        $value = trim($raw);
        $upper = mb_strtoupper($value);

        // 이미 내부 표준값이면 그대로 통과시킨다.
        $known = match ($field) {
            'gender' => Taxonomy::GENDERS,
            'day_type' => Taxonomy::DAY_TYPES,
            'time_band' => Taxonomy::TIME_BANDS,
            'age_band' => Taxonomy::AGE_BANDS,
            'housing_type' => Taxonomy::HOUSING_TYPES,
            'school_type' => Taxonomy::SCHOOL_TYPES,
            'category' => array_keys(Taxonomy::ACADEMY_CATEGORIES),
            default => [],
        };

        if (in_array($value, $known, true)) {
            return $value;
        }

        if (isset($dict[$upper])) {
            return $dict[$upper];
        }

        if (isset($dict[$value])) {
            return $dict[$value];
        }

        // 공백만 다른 표기("70대 이상" ↔ "70대이상")도 같게 본다.
        $condensed = preg_replace('/\s+/u', '', $value);

        foreach ($dict as $key => $mapped) {
            if (preg_replace('/\s+/u', '', (string) $key) === $condensed) {
                return $mapped;
            }
        }

        // 시간대가 "13" 같은 시(hour) 로 오면 구간으로 접는다.
        if ($field === 'time_band' && is_numeric($value)) {
            return $this->hourToBand((int) $value);
        }

        // 연령이 "35" 처럼 실제 나이로 오면 10년 단위로 접는다.
        if ($field === 'age_band' && is_numeric($value)) {
            return $this->ageToBand((int) $value);
        }

        return null;
    }

    public function hourToBand(int $hour): ?string
    {
        return match (true) {
            $hour >= 6 && $hour <= 10 => 'morning',
            $hour >= 11 && $hour <= 14 => 'lunch',
            $hour >= 15 && $hour <= 17 => 'afternoon',
            $hour >= 18 && $hour <= 20 => 'evening',
            $hour >= 21 || $hour <= 0 => 'night',
            default => null,
        };
    }

    public function ageToBand(int $age): string
    {
        return match (true) {
            $age < 10 => 'under10',
            $age >= 70 => '70s_over',
            default => intdiv($age, 10) * 10 .'s',
        };
    }
}
