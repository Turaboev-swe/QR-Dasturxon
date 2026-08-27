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

// QR "bridge" link — printed table QR codes encode this plain https://
// URL instead of a t.me deep link directly. Some third-party QR scanner
// apps (anything other than the phone's native camera) fail to hand a
// t.me:// deep link off to Telegram; a plain https:// URL always opens,
// then redirects on to the real Mini App start link. `{startParam}` is
// forwarded as-is to Telegram — it is never resolved/validated here
// (that already happens later, inside the Mini App, via TableResolver;
// see app/Services/TableResolver.php), this route is just a redirect.
Route::get('/t/{startParam}', function (string $startParam) {
    $target = 'https://t.me/'.config('services.telegram.bot_username')
        .'/'.config('services.telegram.miniapp_short_name')
        .'?startapp='.$startParam;

    return redirect()->away($target);
})->where('startParam', 'r\d+_t.+');
