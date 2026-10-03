<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/radar/{company?}', function (int $company = 1) {
    return view('radar', ['companyId' => $company]);
})->name('radar');
