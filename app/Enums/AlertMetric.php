<?php

namespace App\Enums;

enum AlertMetric: string
{
    case StressProbability = 'stress_probability';
    case MarginAtRisk = 'margin_at_risk';
    case SupplierOvercharge = 'supplier_overcharge';
}
