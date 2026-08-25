<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('miniapp');
});

// Staff-facing pages: deliberately not linked from the customer page or
// exposed in its UI — a cashier/owner reaches these via a direct URL the
// restaurant gives them, not by tapping a tab a random customer can see.
Route::get('/staff', function () {
    return view('staff');
});

Route::get('/owner', function () {
    return view('owner');
});
