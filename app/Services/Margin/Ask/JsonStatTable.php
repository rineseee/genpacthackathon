<?php

namespace App\Services\Margin\Ask;

use InvalidArgumentException;

/**
 * Read access to a JSON-stat 2.0 dataset, the format the ASKdata PxWeb API returns.
 */
final readonly class JsonStatTable
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data) {}

    /**
     * Category ids of a dimension, in dataset order.
     *
     * @return list<string>
     */
    public function categories(string $dimension): array
    {
        $index = $this->dimension($dimension)['category']['index'];
        asort($index);

        return array_map('strval', array_keys($index));
    }

    public function label(string $dimension, string $category): string
    {
        return (string) ($this->dimension($dimension)['category']['label'][$category] ?? $category);
    }

    /**
     * The value at one category of every dimension, or null when the cell is empty.
     *
     * @param  array<string, string>  $categories  Category id keyed by dimension id.
     */
    public function value(array $categories): ?float
    {
        $position = 0;

        foreach ($this->data['id'] as $axis => $dimension) {
            if (! isset($categories[$dimension])) {
                throw new InvalidArgumentException("Missing category for dimension [{$dimension}].");
            }

            $index = $this->dimension($dimension)['category']['index'][$categories[$dimension]] ?? null;

            if ($index === null) {
                return null;
            }

            $position = $position * $this->data['size'][$axis] + $index;
        }

        $value = $this->data['value'][$position] ?? null;

        return $value === null ? null : (float) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function dimension(string $dimension): array
    {
        return $this->data['dimension'][$dimension] ?? throw new InvalidArgumentException("Unknown dimension [{$dimension}].");
    }
}
