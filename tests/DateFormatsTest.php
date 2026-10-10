<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\BooleanFilterExpression;
use ReynoTECH\QueryBuilderCustom\Casts\DateCast;
use ReynoTECH\QueryBuilderCustom\Casts\DateTimeCast;
use ReynoTECH\QueryBuilderCustom\DateFormats;
use ReynoTECH\QueryBuilderCustom\Filters\DateFilter;
use ReynoTECH\QueryBuilderCustom\Filters\DateTimeFilter;
use ReynoTECH\QueryBuilderCustom\SpatieV2FilterConditions;

final class DateFormatsTest extends TestCase
{
    private array $previousConfig;
    private string $previousLocale;

    protected function setUp(): void
    {
        $this->previousConfig = $GLOBALS['__test_config'];
        $this->previousLocale = \Carbon\Carbon::getLocale();
        $GLOBALS['__test_config'] = [];
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        \Carbon\Carbon::setLocale($this->previousLocale);
        $GLOBALS['__test_config'] = $this->previousConfig;
    }

    private function configure(): void
    {
        config([
            'query_builder_custom.dates.date' => 'm/d/Y',
            'query_builder_custom.dates.dateTime' => 'm/d/Y h:i:s A',
            'query_builder_custom.dates.month' => 'm.Y',
            'query_builder_custom.dates.value.date' => 'Y-m-d',
            'query_builder_custom.dates.value.dateTime' => 'Y-m-d H:i:s',
            'query_builder_custom.dates.value.month' => 'Y-m',
            'query_builder_custom.dates.filter.date' => 'd.m.Y',
            'query_builder_custom.dates.filter.dateTime' => 'd.m.Y H:i:s',
            'query_builder_custom.dates.filter.month' => 'Y-m',
        ]);
    }

    public function test_exports_one_contract_with_separate_display_value_and_filter_patterns(): void
    {
        $this->configure();
        $result = DateFormats::frontendConfig();
        $this->assertSame('MM/DD/YYYY', $result['date']);
        $this->assertSame('MM/DD/YYYY hh:mm:ss A', $result['dateTime']);
        $this->assertSame('MM.YYYY', $result['month']);
        $this->assertSame(['date' => 'YYYY-MM-DD', 'dateTime' => 'YYYY-MM-DD HH:mm:ss', 'month' => 'YYYY-MM'], $result['value']);
        $this->assertSame(['date' => 'DD.MM.YYYY', 'dateTime' => 'DD.MM.YYYY HH:mm:ss', 'month' => 'YYYY-MM'], $result['filter']);
    }

    public function test_legacy_settings_and_historical_cast_defaults_are_preserved(): void
    {
        $this->assertSame('d/m/Y', DateFormats::format('date', 'filter'));
        $this->assertSame('12/02/26', (new DateCast())->get(null, 'date', '2026-02-12', []));
        config(['app.date_format_solo' => 'm.d.Y', 'app.date_format' => 'm.d.Y H:i']);
        $this->assertSame('MM.DD.YYYY', DateFormats::frontendConfig()['date']);
        $this->assertSame('02.12.2026', (new DateCast())->get(null, 'date', '2026-02-12', []));
    }

    public function test_global_formats_round_trip_casts_and_allow_per_cast_overrides(): void
    {
        $this->configure();
        $date = new DateCast(true);
        $time = new DateTimeCast(true);
        $this->assertSame('2026-07-08', $date->get(null, 'date', '2026-07-08', []));
        $this->assertSame('2026-07-08', $date->set(null, 'date', '2026-07-08', []));
        $this->assertSame('2026-07-08 13:05:06', $time->get(null, 'time', '2026-07-08 13:05:06', []));
        $this->assertSame('2026-07-08 13:05:06', $time->set(null, 'time', '2026-07-08 13:05:06', []));
        $custom = new DateCast(true, 'd/m/Y', 'd/m/Y');
        $this->assertSame('08/07/2026', $custom->get(null, 'date', '2026-07-08', []));
        $this->assertSame('2026-07-08', $custom->set(null, 'date', '08/07/2026', []));
    }

    public function test_shared_cast_meridiem_values_do_not_change_with_carbon_locale(): void
    {
        \Carbon\Carbon::setLocale('es');
        config(['query_builder_custom.dates.value.dateTime' => 'm/d/Y h:i:s A']);
        $cast = new DateTimeCast(true);
        $this->assertSame('07/08/2026 01:05:06 PM', $cast->get(null, 'time', '2026-07-08 13:05:06', []));
        $this->assertSame('2026-07-08 13:05:06', $cast->set(null, 'time', '07/08/2026 01:05:06 PM', []));
    }

    public function test_converts_numeric_tokens_and_escaped_literals(): void
    {
        $this->assertSame('YYYY-MM-DD[T]HH:mm:ss', DateFormats::toDayjs('Y-m-d\\TH:i:s'));
        $this->assertSame('D/M/YY h:mm a', DateFormats::toDayjs('j/n/y g:i a'));
    }

    public function test_rejects_unportable_tokens_instead_of_silently_corrupting_them(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateFormats::toDayjs('c');
    }

    private function database(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        // Existing month operators use MySQL MONTH/YEAR. Register equivalents for QA.
        $pdo = $capsule->getConnection()->getPdo();
        $register = method_exists($pdo, 'createFunction') ? 'createFunction' : 'sqliteCreateFunction';
        $pdo->{$register}('MONTH', fn ($value) => $value === null ? null : (int) substr($value, 5, 2), 1);
        $pdo->{$register}('YEAR', fn ($value) => $value === null ? null : (int) substr($value, 0, 4), 1);
        $capsule->getConnection()->getSchemaBuilder()->create('date_contract_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->dateTime('event_at')->nullable();
        });
        Capsule::table('date_contract_events')->insert([
            ['event_at' => '2026-07-08 09:00:00'],
            ['event_at' => '2026-07-08 13:05:06'],
            ['event_at' => '2026-07-09 14:00:00'],
            ['event_at' => '2026-08-07 10:00:00'],
            ['event_at' => null],
        ]);
        config([
            'query_builder_custom.dates.date' => 'm/d/Y',
            'query_builder_custom.dates.dateTime' => 'm/d/Y h:i:s A',
            'query_builder_custom.dates.month' => 'Y-m',
        ]);
    }

    private function apply(DateFilter $filter, mixed $value): array
    {
        $query = DateContractEvent::query();
        $filter($query, $value, 'event_at');
        return $query->orderBy('id')->pluck('id')->all();
    }

    public function test_configured_date_filters_keep_day_comparisons(): void
    {
        $this->database();
        $this->assertSame([1, 2], $this->apply(new DateFilter(), 'eq|07/08/2026'));
        $this->assertSame([1, 2, 3], $this->apply(new DateFilter(), 'bw|07/08/2026,07/09/2026'));
        $this->assertSame([1, 2, 4], $this->apply(new DateFilter(), 'in|07/08/2026,08/07/2026'));
    }

    public function test_datetime_comparisons_preserve_hours_minutes_and_seconds(): void
    {
        $this->database();
        $filter = new DateTimeFilter();
        $this->assertSame([2], $this->apply($filter, 'eq|07/08/2026 01:05:06 PM'));
        $this->assertSame([2, 3, 4], $this->apply($filter, 'gte|07/08/2026 01:05:06 PM'));
        $this->assertSame([2, 3], $this->apply($filter, 'bw|07/08/2026 01:05:06 PM,07/09/2026 02:00:00 PM'));
        $this->assertSame([1, 3], $this->apply($filter, 'in|07/08/2026 09:00:00 AM,07/09/2026 02:00:00 PM'));
    }

    public function test_filter_formats_can_change_without_recreating_the_filter(): void
    {
        $this->database();
        config(['query_builder_custom.dates.filter.dateTime' => 'Y-m-d H:i:s']);
        $filter = new DateTimeFilter();
        $this->assertSame([2], $this->apply($filter, 'eq|2026-07-08 13:05:06'));
        config(['query_builder_custom.dates.filter.dateTime' => 'd.m.Y H:i:s']);
        $this->assertSame([2], $this->apply($filter, 'eq|08.07.2026 13:05:06'));
        $this->assertSame([2], $this->apply(new DateTimeFilter('Y/m/d H:i:s'), 'eq|2026/07/08 13:05:06'));
    }

    public function test_custom_month_formats_work_for_equality_ranges_and_nulls(): void
    {
        $this->database();
        $this->assertSame([1, 2, 3], $this->apply(new DateFilter(), 'my|2026-07'));
        $this->assertSame([1, 2, 3, 4], $this->apply(new DateFilter(), 'bmy|2026-07,2026-08'));
        $this->assertSame([5], $this->apply(new DateFilter(), 'myn|null'));
    }

    public function test_boolean_expressions_and_spatie_v2_share_the_datetime_parser(): void
    {
        $this->database();
        config(['query_builder_custom.dates.filter.dateTime' => 'Y-m-d H:i:s']);
        $expression = BooleanFilterExpression::fromPayload(['type' => 'condition', 'op' => 'eq', 'value' => '2026-07-08 13:05:06']);
        $this->assertSame([2], $this->apply(new DateTimeFilter(), $expression));
        $conditions = new SpatieV2FilterConditions([
            ['join' => 'and', 'operator' => 'gte', 'value' => '2026-07-08 13:05:06'],
            ['join' => 'and', 'operator' => 'lte', 'value' => '2026-07-09 14:00:00'],
        ]);
        $this->assertSame([2, 3], $this->apply(new DateTimeFilter(), $conditions));
    }

    public function test_invalid_datetime_expression_is_rejected_strictly(): void
    {
        $this->database();
        $this->expectException(\ReynoTECH\QueryBuilderCustom\Exceptions\InvalidFilterValue::class);
        $expression = BooleanFilterExpression::fromPayload(['type' => 'condition', 'op' => 'eq', 'value' => '02/31/2026 01:05:06 PM']);
        $this->apply(new DateTimeFilter(), $expression);
    }

    public function test_relative_datetime_periods_include_the_entire_calendar_day(): void
    {
        $this->database();
        config(['app.timezone' => 'UTC']);
        CarbonImmutable::setTestNow('2026-07-08 12:00:00 UTC');
        $this->assertSame([1, 2], $this->apply(new DateTimeFilter(), 'relative|today'));
    }
}

final class DateContractEvent extends Model
{
    protected $table = 'date_contract_events';
    public $timestamps = false;
}
