<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CompanyResource::collection(Company::query()->orderBy('name')->orderBy('id')->get());
    }

    public function show(Company $company): CompanyResource
    {
        return new CompanyResource($company);
    }
}
