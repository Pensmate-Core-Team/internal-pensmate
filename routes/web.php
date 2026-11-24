<?php

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContentAssetController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\MemberActivationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\TimelineBoardController;
use App\Http\Controllers\TimelineContentController;
use App\Http\Controllers\TimelineListController;
use App\Http\Controllers\TimelineController;
use App\Http\Middleware\EnsureActiveMember;
use App\Http\Middleware\ProtectActivationResponse;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/contents');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});
Route::middleware(ProtectActivationResponse::class)->group(function () {
    Route::get('/activate/{token}', [ActivationController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:60,1')->name('activation.show');
    Route::post('/activate/{token}', [ActivationController::class, 'store'])->where('token', '[A-Za-z0-9]{64}')->middleware(['guest', 'throttle:10,1'])->name('activation.store');
});
Route::middleware(['auth', EnsureActiveMember::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');
    Route::get('/timeline/list', [TimelineListController::class, '__invoke'])->name('timeline.list');
    Route::get('/timeline/board', [TimelineBoardController::class, '__invoke'])->name('timeline.board');
    Route::get('/integrations/timeline/contents', TimelineContentController::class)->name('timeline.contents');
    Route::resource('contents', ContentController::class)->except('destroy');
    Route::post('/contents/{content}/transition', [ContentController::class, 'transition'])->name('contents.transition');
    Route::get('/contents/{content}/media', [ContentController::class, 'media'])->name('contents.media');
    Route::get('/contents/{content}/download', [ContentController::class, 'download'])->name('contents.download');
    Route::get('/contents/{content}/assets/{asset}', [ContentAssetController::class, 'show'])->name('contents.assets.show');
    Route::get('/contents/{content}/assets/{asset}/download', [ContentAssetController::class, 'download'])->name('contents.assets.download');
    Route::post('/contents/{content}/references', [ReferenceController::class, 'store'])->name('references.store');
    Route::put('/contents/{content}/references/{reference}', [ReferenceController::class, 'update'])->name('references.update');
    Route::delete('/contents/{content}/references/{reference}', [ReferenceController::class, 'destroy'])->name('references.destroy');
    Route::middleware('can:manage-members')->group(function () {
        Route::post('/members/{member}/activation-link', [MemberActivationController::class, 'store'])->middleware([ProtectActivationResponse::class, 'throttle:10,1'])->name('members.activation.store');
        Route::delete('/members/{member}/activation-link', [MemberActivationController::class, 'destroy'])->name('members.activation.destroy');
        Route::resource('members', MemberController::class);
    });
});
