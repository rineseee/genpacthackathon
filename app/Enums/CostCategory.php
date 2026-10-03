<?php

namespace App\Enums;

enum CostCategory: string
{
    case Ingredients = 'ingredients';
    case Packaging = 'packaging';
    case Energy = 'energy';
    case Transport = 'transport';
    case Wages = 'wages';
    case Rent = 'rent';
    case Other = 'other';

    /**
     * Whether spend in this category normally moves with sales volume.
     */
    public function scalesWithVolume(): bool
    {
        return in_array($this, [self::Ingredients, self::Packaging], true);
    }
}
