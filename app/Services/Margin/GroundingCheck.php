<?php

namespace App\Services\Margin;

/**
 * Blocks any number in generated text that the calculation engine did not produce.
 *
 * Every narrative (template today, LLM tomorrow) passes through here with the facts it was
 * allowed to use. A number in the text counts as grounded when some fact, rounded to the
 * same number of decimals, equals it.
 */
final class GroundingCheck
{
    /**
     * @param  list<float|int>  $facts
     * @param  list<string>  $names  Names copied verbatim from the data (supplier, product, statistic); numbers inside them are not claims.
     * @return list<string> The numbers in the text that no fact supports.
     */
    public function ungroundedNumbers(string $text, array $facts, array $names = []): array
    {
        $text = str_replace(array_filter($names, fn (string $name): bool => $name !== ''), ' ', $text);
        preg_match_all('/(?<![\w.])-?\d[\d,]*(?:\.\d+)?/u', $text, $matches);
        $ungrounded = [];

        foreach ($matches[0] as $token) {
            $number = (float) str_replace(',', '', $token);
            $decimals = str_contains($token, '.') ? strlen(substr(strrchr($token, '.'), 1)) : 0;

            if (! $this->isSupported($number, $decimals, $facts)) {
                $ungrounded[] = $token;
            }
        }

        return $ungrounded;
    }

    /**
     * @param  list<float|int>  $facts
     * @param  list<string>  $names
     */
    public function isGrounded(string $text, array $facts, array $names = []): bool
    {
        return $this->ungroundedNumbers($text, $facts, $names) === [];
    }

    /**
     * @param  list<float|int>  $facts
     */
    private function isSupported(float $number, int $decimals, array $facts): bool
    {
        foreach ($facts as $fact) {
            if (abs(round((float) $fact, $decimals) - $number) < 1e-9 || abs(round(abs((float) $fact), $decimals) - abs($number)) < 1e-9) {
                return true;
            }
        }

        return false;
    }
}
