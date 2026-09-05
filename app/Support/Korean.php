<?php

namespace App\Support;

/**
 * 자동 생성 문장에 붙는 조사를 받침에 맞춰 고른다.
 * ("한식음식점이", "커피·음료가" / "여성으로", "남성으로")
 */
class Korean
{
    /** 지원하는 조사 쌍: [받침 있음, 받침 없음] */
    private const PAIRS = [
        '이/가' => ['이', '가'],
        '은/는' => ['은', '는'],
        '을/를' => ['을', '를'],
        '과/와' => ['과', '와'],
        '으로/로' => ['으로', '로'],
        '이다/다' => ['이다', '다'],
    ];

    /** 단어 뒤에 알맞은 조사를 붙여 돌려준다. */
    public static function withJosa(string $word, string $pair): string
    {
        return $word.self::josa($word, $pair);
    }

    /** 알맞은 조사만 돌려준다. */
    public static function josa(string $word, string $pair): string
    {
        [$withBatchim, $withoutBatchim] = self::PAIRS[$pair] ?? self::PAIRS['이/가'];

        $final = self::finalConsonant($word);

        if ($final === null) {
            // 한글이 아니면(숫자·영문 등) 받침 없는 형태를 쓴다.
            return $withoutBatchim;
        }

        // '으로/로' 는 ㄹ 받침도 받침 없는 형태를 쓴다. (예: 서울로)
        if ($pair === '으로/로' && $final === 8) {
            return $withoutBatchim;
        }

        return $final === 0 ? $withoutBatchim : $withBatchim;
    }

    /**
     * 마지막 글자의 종성 인덱스. 0이면 받침 없음, null이면 한글이 아님.
     * 한글 음절 = 0xAC00 + (초성 × 21 + 중성) × 28 + 종성
     */
    private static function finalConsonant(string $word): ?int
    {
        $word = rtrim($word);

        if ($word === '') {
            return null;
        }

        $last = mb_substr($word, -1);
        $code = mb_ord($last, 'UTF-8');

        if ($code === false || $code < 0xAC00 || $code > 0xD7A3) {
            return null;
        }

        return ($code - 0xAC00) % 28;
    }
}
