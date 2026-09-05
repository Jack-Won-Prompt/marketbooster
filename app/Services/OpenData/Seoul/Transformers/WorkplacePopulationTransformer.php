<?php

namespace App\Services\OpenData\Seoul\Transformers;

use App\Support\Period;

/**
 * VwsmAdstrdWrcPopltnW — 서울시 상권분석서비스(직장인구-행정동)
 *
 * 상주인구와 마찬가지로 성별 × 연령 교차값(MAG_/FAG_)을 직접 제공한다.
 */
class WorkplacePopulationTransformer extends SeoulRowTransformer
{
    public function transform(array $row, Period $period): array
    {
        $regionCode = $this->regionCode($row);

        if ($regionCode === null) {
            return [];
        }

        $columns = $period->columns();
        $rows = [];

        foreach (self::AGE_BANDS as $suffix => $ageBand) {
            foreach (['M' => 'MAG', 'F' => 'FAG'] as $gender => $prefix) {
                $value = (int) round($this->num($row, "{$prefix}_{$suffix}_WRC_POPLTN_CO"));

                if ($value <= 0) {
                    continue;
                }

                $rows[] = [
                    'region_code' => $regionCode,
                    'gender' => $gender,
                    'age_band' => $ageBand,
                    'population' => $value,
                ] + $columns;
            }
        }

        return ['workplace_population' => $rows];
    }
}
