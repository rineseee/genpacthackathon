<?php

namespace App\Enums;

enum SimulationScenario: string
{
    /** Borrowed projections for every driver. */
    case Baseline = 'baseline';

    /** The approximate 2022 shock replayed on today's cost base. */
    case Replay2022 = 'replay_2022';

    /** Baseline plus the engine's recommended plan. */
    case Plan = 'plan';
}
