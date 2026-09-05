<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 발급받은 인증키를 .env 에 안전하게 써 넣는다.
 *
 * .env 를 직접 편집하다 형식이 깨지는 일을 막고, 키를 화면에 그대로 노출하지 않는다.
 * 키를 인자로 주지 않으면 가려진 입력으로 받는다(셸 히스토리에 남지 않는다).
 */
class SetApiKeyCommand extends Command
{
    protected $signature = 'opendata:key
        {portal? : seoul | datago}
        {key? : 인증키 (생략하면 화면에 표시되지 않게 입력받습니다)}
        {--show : 현재 설정된 키를 가려서 보여줍니다}';

    protected $description = '공공데이터 인증키를 .env 에 설정합니다.';

    /** 포털 => [.env 변수명, 표시명, 발급 안내] */
    private const PORTALS = [
        'seoul' => [
            'SEOUL_OPENAPI_KEY',
            '서울 열린데이터광장',
            'https://data.seoul.go.kr/together/mypage/actkeyMain.do 에서 [일반 인증키] 발급',
        ],
        'datago' => [
            'OPENDATA_SERVICE_KEY',
            '공공데이터포털 (data.go.kr)',
            'https://www.data.go.kr/iim/api/selectAcountList.do 의 일반 인증키 중 Decoding 값',
        ],
    ];

    public function handle(): int
    {
        if ($this->option('show') || ! $this->argument('portal')) {
            return $this->show();
        }

        $portal = strtolower((string) $this->argument('portal'));

        if (! isset(self::PORTALS[$portal])) {
            $this->error("알 수 없는 포털입니다: {$portal} (사용 가능: ".implode(', ', array_keys(self::PORTALS)).')');

            return self::FAILURE;
        }

        [$variable, $label, $guide] = self::PORTALS[$portal];

        $key = trim((string) ($this->argument('key') ?: $this->secret("{$label} 인증키를 붙여넣으세요")));

        if ($key === '') {
            $this->error('인증키가 비어 있습니다.');
            $this->line('  '.$guide);

            return self::FAILURE;
        }

        // Encoding 키(%2B, %3D 등이 섞인 형태)를 넣는 실수를 잡아 준다.
        if ($portal === 'datago' && preg_match('/%[0-9A-Fa-f]{2}/', $key)) {
            $this->warn('  URL 인코딩된(Encoding) 키로 보입니다. data.go.kr 에서는 Decoding 키를 쓰세요.');

            if (! $this->confirm('  그대로 저장할까요?', false)) {
                return self::FAILURE;
            }
        }

        $this->write($variable, $key);

        $this->info("{$label} 인증키를 저장했습니다: ".$this->mask($key));
        $this->call('config:clear');
        $this->newLine();
        $this->line('이어서 확인해 보세요:  php artisan opendata:check');

        return self::SUCCESS;
    }

    private function show(): int
    {
        $this->newLine();

        foreach (self::PORTALS as $portal => [$variable, $label, $guide]) {
            $value = (string) env($variable, '');

            $this->line(sprintf('<options=bold>%s</> (%s)', $label, $variable));

            if ($value === '') {
                $this->warn('  미설정 — '.$guide);
                $this->line("  설정:  php artisan opendata:key {$portal}");
            } else {
                $this->info('  '.$this->mask($value));
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }

    /** .env 의 해당 줄만 바꾼다. 줄이 없으면 끝에 덧붙인다. */
    private function write(string $variable, string $value): void
    {
        $path = base_path('.env');
        $contents = file_get_contents($path);

        // 값에 공백이나 #, " 가 있으면 따옴표로 감싼다.
        $escaped = preg_match('/[\s#"\']/', $value)
            ? '"'.str_replace('"', '\"', $value).'"'
            : $value;

        $line = $variable.'='.$escaped;
        $pattern = '/^'.preg_quote($variable, '/').'=.*$/m';

        $contents = preg_match($pattern, $contents)
            ? preg_replace($pattern, $line, $contents, 1)
            : rtrim($contents, "\r\n")."\n".$line."\n";

        file_put_contents($path, $contents);
    }

    /** 앞뒤 몇 글자만 남기고 가린다. */
    private function mask(string $key): string
    {
        $length = mb_strlen($key);

        if ($length <= 12) {
            return str_repeat('*', $length)." ({$length}자)";
        }

        return mb_substr($key, 0, 6).str_repeat('*', 8).mb_substr($key, -4)." ({$length}자)";
    }
}
