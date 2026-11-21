<?php

namespace Tests\Unit;

use App\Services\KitPricing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KitPricingTest extends TestCase
{
    public function test_the_target_is_split_in_proportion_to_the_weights(): void
    {
        $this->assertSame(['a' => 2000, 'b' => 1000], KitPricing::allocate(['a' => 4000, 'b' => 2000], 3000));
    }

    /**
     * @param  array<int, int>  $weights
     */
    #[DataProvider('awkwardSplits')]
    public function test_the_parts_always_add_up_to_the_target_exactly(array $weights, int $target): void
    {
        $parts = KitPricing::allocate($weights, $target);

        $this->assertSame($target, array_sum($parts));
        $this->assertSame(array_keys($weights), array_keys($parts));
    }

    /**
     * @return array<string, array{array<int, int>, int}>
     */
    public static function awkwardSplits(): array
    {
        return [
            'thirds' => [[100, 100, 100], 100],
            'uneven' => [[3333, 1, 777], 9999],
            'single line' => [[500], 1234],
            'target below the weights' => [[1000, 2000, 3000], 7],
            'zero target' => [[1000, 2000], 0],
        ];
    }

    public function test_largest_remainders_get_the_spare_cents(): void
    {
        $this->assertSame([34, 33, 33], KitPricing::allocate([1, 1, 1], 100));
    }

    public function test_zero_weights_are_split_evenly(): void
    {
        $this->assertSame([50, 50], KitPricing::allocate([0, 0], 100));
    }

    public function test_no_lines_means_nothing_to_allocate(): void
    {
        $this->assertSame([], KitPricing::allocate([], 500));
    }
}
