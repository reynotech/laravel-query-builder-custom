<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests\Casts;

use DateTime;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\Casts\DateCast;

final class DateCastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.date_format_solo' => 'd/m/Y',
        ]);
    }

    public function test_get_formats_storage_date(): void
    {
        $cast = new DateCast();

        $result = $cast->get(null, 'date', '2026-02-12', []);

        $this->assertSame('12/02/2026', $result);
    }

    public function test_get_uses_model_date_format_when_attribute_is_date(): void
    {
        $cast = new DateCast();
        $model = new class {
            public function getDates(): array
            {
                return ['published_at'];
            }

            public function getDateFormat(): string
            {
                return 'Y/m/d';
            }
        };

        $result = $cast->get($model, 'published_at', '2026/02/12', []);

        $this->assertSame('12/02/2026', $result);
    }

    public function test_set_parses_input_when_enabled(): void
    {
        $cast = new DateCast(true, 'd/m/Y', 'd/m/Y');

        $result = $cast->set(null, 'date', '12/02/2026', []);

        $this->assertSame('2026-02-12', $result);
    }

    public function test_set_normalizes_datetimeinterface_when_parsing_disabled(): void
    {
        $cast = new DateCast();

        $result = $cast->set(null, 'date', new DateTime('2026-02-12 09:30:00'), []);

        $this->assertSame('2026-02-12', $result);
    }
}
