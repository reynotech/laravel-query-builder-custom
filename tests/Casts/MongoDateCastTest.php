<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests\Casts;

use DateTime;
use DateTimeZone;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\Casts\MongoDateCast;

final class MongoDateCastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(UTCDateTime::class)) {
            $this->markTestSkipped('MongoDB extension not installed.');
        }

        config([
            'app.date_format_solo' => 'd/m/Y',
        ]);
    }

    public function test_get_formats_utc_datetime(): void
    {
        $cast = new MongoDateCast();
        $utc = new UTCDateTime((new DateTime('2026-02-12 00:00:00', new DateTimeZone('UTC')))->getTimestamp() * 1000);

        $result = $cast->get(null, 'date', $utc, []);

        $this->assertSame('12/02/2026', $result);
    }

    public function test_set_parses_input_to_utc_datetime_by_default(): void
    {
        $cast = new MongoDateCast();

        $result = $cast->set(null, 'date', '12/02/2026', []);

        $this->assertInstanceOf(UTCDateTime::class, $result);
        $this->assertSame('2026-02-12', $result->toDateTime()->format('Y-m-d'));
    }

    public function test_set_normalizes_datetime_interface_when_parsing_disabled(): void
    {
        $cast = new MongoDateCast(false);

        $result = $cast->set(null, 'date', new DateTime('2026-02-12 09:30:00', new DateTimeZone('UTC')), []);

        $this->assertInstanceOf(UTCDateTime::class, $result);
        $this->assertSame('2026-02-12', $result->toDateTime()->format('Y-m-d'));
    }
}
