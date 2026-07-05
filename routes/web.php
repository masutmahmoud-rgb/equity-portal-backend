<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'Laravel is working!';
});

Route::redirect('/admin', '/admin/ownership-manual');
Route::redirect('/ownership-manual', '/admin/ownership-manual');
Route::view('/admin/ownership-manual', 'admin.ownership-manual')->name('admin.ownership-manual');