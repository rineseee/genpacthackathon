<?php

namespace Tests\Unit\Services\Margin;

use App\Services\Margin\GroundingCheck;
use PHPUnit\Framework\TestCase;

class GroundingCheckTest extends TestCase
{
    public function test_accepts_text_whose_numbers_all_come_from_facts(): void
    {
        $text = 'Profit falls from €8,000 to about €723 within 6 months, a 64.0% chance of stress.';

        $this->assertTrue((new GroundingCheck)->isGrounded($text, [8000, 723.4, 6, 0.64 * 100]));
    }

    public function test_reports_numbers_the_engine_did_not_produce(): void
    {
        $text = 'Your supplier raised flour 14% while the market rose 3%.';

        $this->assertSame(['3'], (new GroundingCheck)->ungroundedNumbers($text, [14.0]));
    }

    public function test_ignores_numbers_inside_names_copied_from_the_data(): void
    {
        $text = 'The official HICP 01.1.1 Bread and cereals index moved 4.1%.';

        $this->assertFalse((new GroundingCheck)->isGrounded($text, [4.1]));
        $this->assertTrue((new GroundingCheck)->isGrounded($text, [4.1], ['HICP 01.1.1 Bread and cereals']));
    }

    public function test_matches_negative_amounts_written_with_a_leading_sign(): void
    {
        $this->assertTrue((new GroundingCheck)->isGrounded('Range -€2,353 to €3,862.', [-2353.2, 3861.9]));
    }
}
