<?php

namespace App\Enums;

enum OfferKind: string
{
    case AlternativeSupplier = 'alternative_supplier';
    case FixedPrice = 'fixed_price';
}
