<?php

namespace App\Services;

/**
 * Spreads a kit's flat price over the lines it expands into.
 */
final class KitPricing
{
    /**
     * Split a target total across lines in proportion to their standard prices.
     *
     * Uses the largest-remainder method so the parts always add up to exactly the target.
     *
     * @param  array<int, int>  $weights  Standard price of each line, in cents.
     * @return array<int, int> Allocated cents per line, with the same keys.
     */
    public static function allocate(array $weights, int $target): array
    {
        if ($weights === []) {
            return [];
        }

        $total = array_sum($weights);
        $weights = $total > 0 ? $weights : array_fill_keys(array_keys($weights), 1);
        $total = array_sum($weights);

        $shares = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $exact = $target * $weight / $total;
            $shares[$key] = (int) floor($exact);
            $remainders[$key] = $exact - $shares[$key];
        }

        $left = $target - array_sum($shares);
        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, $left) as $key) {
            $shares[$key]++;
        }

        return $shares;
    }
}
