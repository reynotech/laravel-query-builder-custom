<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests\Casts;

use DateTime;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\Casts\DateTimeCast;

final class DateTimeCastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.date_format' => 'd/m/Y H:i',
        ]);
    }

    public function test_get_formats_storage_datetime(): void
    {
        $cast = new DateTimeCast();

        $result = $cast->get(null, 'published_at', '2026-02-12 13:45:00', []);

        $this->assertSame('12/02/2026 13:45', $result);
    }

    public function test_get_uses_model_date_format_when_attribute_is_date(): void
    {
        $cast = new DateTimeCast();
        $model = new class {
            public function getDates(): array
            {
                return ['published_at'];
            }

            public function getDateFormat(): string
            {
                return 'Y/m/d H:i:s';
            }
        };

        $result = $cast->get($model, 'published_at', '2026/02/12 13:45:00', []);

        $this->assertSame('12/02/2026 13:45', $result);
    }

    public function test_set_parses_input_when_enabled(): void
    {
        $cast = new DateTimeCast(true, 'd/m/Y H:i', 'd/m/Y H:i');

        $result = $cast->set(null, 'published_at', '12/02/2026 13:45', []);

        $this->assertSame('2026-02-12 13:45:00', $result);
    }

    public function test_set_normalizes_datetimeinterface_when_parsing_disabled(): void
    {
        $cast = new DateTimeCast();

        $result = $cast->set(null, 'published_at', new DateTime('2026-02-12 13:45:00'), []);

        $this->assertSame('2026-02-12 13:45:00', $result);
    }
}
