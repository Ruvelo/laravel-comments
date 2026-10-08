<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ruvelo\Comments\Http\Controllers\CommentController;
use Ruvelo\Comments\Http\Controllers\ModerationController;
use Ruvelo\Comments\Http\Middleware\EnsureSignedIn;

// Every route answers JSON when asked (Accept: application/json), so these
// serve the Blade component's forms, its script, and SPAs alike.
Route::group([
    'prefix' => config('comments.path', 'comments'),
    'domain' => config('comments.domain'),
    'middleware' => config('comments.middleware', ['web']),
    'as' => 'comments.',
], function () {
    Route::get('threads/{type}/{id}', [CommentController::class, 'index'])->name('index');
    Route::get('{commentId}', [CommentController::class, 'show'])->whereNumber('commentId')->name('show');

    Route::middleware(EnsureSignedIn::class)->group(function () {
        Route::post('threads/{type}/{id}', [CommentController::class, 'store'])->name('store');
        Route::post('preview', [CommentController::class, 'preview'])->name('preview');
        Route::patch('{commentId}', [CommentController::class, 'update'])->whereNumber('commentId')->name('update');
        Route::delete('{commentId}', [CommentController::class, 'destroy'])->whereNumber('commentId')->name('destroy');
        Route::post('{commentId}/reactions', [CommentController::class, 'react'])->whereNumber('commentId')->name('react');

        Route::get('moderation/{status?}', [ModerationController::class, 'index'])
            ->whereIn('status', array_keys(ModerationController::STATUSES))->name('moderation');
        Route::post('{commentId}/approve', [ModerationController::class, 'approve'])->whereNumber('commentId')->name('approve');
    });
});
