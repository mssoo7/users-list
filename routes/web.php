<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserImportController;

Route::get('/', [UserImportController::class, 'showTree']);
