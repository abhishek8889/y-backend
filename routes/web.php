<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

if (app()->environment(['local', 'testing'])) {
    Route::get('/check-payment-with-clientsecret', function () {
        return view('stripe.check-payment-with-clientsecret', [
            'publishableKey' => (string) config('stripe.publishable_key', ''),
        ]);
    })->name('stripe.check-payment-with-clientsecret');
}

require __DIR__.'/settings.php';
