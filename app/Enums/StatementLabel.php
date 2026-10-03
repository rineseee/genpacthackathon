<?php

namespace App\Enums;

/**
 * Every number or sentence the platform shows carries one of these labels.
 */
enum StatementLabel: string
{
    case Data = 'data';
    case Forecast = 'forecast';
    case Assumption = 'assumption';
    case AiSuggestion = 'ai_suggestion';
    case NeedsValidation = 'needs_validation';
}
