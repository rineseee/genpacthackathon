<?php

namespace Tests\Unit\Services\Margin\Ask;

use App\Services\Margin\Ask\IndexChainer;
use PHPUnit\Framework\TestCase;

class IndexChainerTest extends TestCase
{
    public function test_follows_published_levels_within_one_base(): void
    {
        $index = (new IndexChainer)->chain(
            ['2026-01' => 120.0, '2026-02' => 121.2, '2026-03' => 123.6],
            ['2026-02' => 1.0, '2026-03' => 2.0],
        );

        $this->assertSame(['2026-01' => 100.0, '2026-02' => 101.0, '2026-03' => 103.0], $index);
    }

    public function test_bridges_a_rebased_index_with_the_published_monthly_change(): void
    {
        // ASK rebased the index in April 2026: the level drops from 142.6 to 106.6 while prices rose 1.1%.
        $index = (new IndexChainer)->chain(
            ['2026-03' => 142.6, '2026-04' => 106.6, '2026-05' => 106.1],
            ['2026-04' => 1.1, '2026-05' => -0.4],
        );

        $this->assertSame(101.1, $index['2026-04']);
        $this->assertEqualsWithDelta(101.1 * 106.1 / 106.6, $index['2026-05'], 0.0001);
    }

    public function test_fills_months_published_only_as_an_annual_change(): void
    {
        $levels = [];
        foreach (range(1, 12) as $month) {
            $levels[sprintf('2025-%02d', $month)] = 100.0;
        }

        $index = (new IndexChainer)->chain($levels, [], ['2026-01' => 7.3]);

        $this->assertSame(107.3, $index['2026-01']);
    }
}
