<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReferenceController;
use App\Http\Middleware\EnsureActiveMember;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/contents');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});
Route::middleware(['auth', EnsureActiveMember::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::resource('contents', ContentController::class)->except('destroy');
    Route::post('/contents/{content}/transition', [ContentController::class, 'transition'])->name('contents.transition');
    Route::get('/contents/{content}/media', [ContentController::class, 'media'])->name('contents.media');
    Route::post('/contents/{content}/references', [ReferenceController::class, 'store'])->name('references.store');
    Route::put('/contents/{content}/references/{reference}', [ReferenceController::class, 'update'])->name('references.update');
    Route::delete('/contents/{content}/references/{reference}', [ReferenceController::class, 'destroy'])->name('references.destroy');
    Route::middleware('can:manage-members')->group(function () {
        Route::resource('members', MemberController::class)->except(['show', 'destroy']);
    });
});
