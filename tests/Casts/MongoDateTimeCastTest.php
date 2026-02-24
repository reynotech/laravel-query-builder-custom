<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests\Casts;

use DateTime;
use DateTimeZone;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\Casts\MongoDateTimeCast;

final class MongoDateTimeCastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(UTCDateTime::class)) {
            $this->markTestSkipped('MongoDB extension not installed.');
        }

        config([
            'app.date_format' => 'd/m/Y H:i',
        ]);
    }

    public function test_get_formats_utc_datetime(): void
    {
        $cast = new MongoDateTimeCast();
        $utc = new UTCDateTime((new DateTime('2026-02-12 13:45:00', new DateTimeZone('UTC')))->getTimestamp() * 1000);

        $result = $cast->get(null, 'published_at', $utc, []);

        $this->assertSame('12/02/2026 13:45', $result);
    }

    public function test_set_parses_input_to_utc_datetime_by_default(): void
    {
        $cast = new MongoDateTimeCast();

        $result = $cast->set(null, 'published_at', '12/02/2026 13:45', []);

        $this->assertInstanceOf(UTCDateTime::class, $result);
        $this->assertSame('2026-02-12 13:45:00', $result->toDateTime()->format('Y-m-d H:i:s'));
    }
}
