<?php

namespace App\Enums;

enum DriverKind: string
{
    case Cpi = 'cpi';
    case Commodity = 'commodity';
    case Energy = 'energy';
    case Fuel = 'fuel';
    case Wages = 'wages';
    case Fx = 'fx';
}
