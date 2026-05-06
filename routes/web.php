<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/cashin-ledger/demo', fn() => view('cashin-ledger'))->name('cashin.ledger.demo');
