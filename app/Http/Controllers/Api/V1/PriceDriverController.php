<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PriceDriverResource;
use App\Models\PriceDriver;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public price drivers: latest value, last-year change and the borrowed projection.
 */
class PriceDriverController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PriceDriverResource::collection(
            PriceDriver::query()->with(['observations', 'projections'])->orderBy('code')->get(),
        );
    }
}
