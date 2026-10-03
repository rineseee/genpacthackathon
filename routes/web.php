<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/radar');

Route::get('/radar/{company?}', function (int $company = 1) {
    return view('radar', ['companyId' => $company]);
})->name('radar');
