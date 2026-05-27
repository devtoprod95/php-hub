<?php

use App\Http\Controllers\SimpleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {
    return view('test');
});

Route::get('/hello', [SimpleController::class, 'hello']);

Route::get('/ssl', function () {
    $isSecure   = request()->secure();
    $statusText = $isSecure ? '🔒 SSL (HTTPS) 연결 상태입니다.' : '⚠️ 일반 HTTP 연결 상태입니다.';

    return "{$statusText}";
});
