<?php

use App\Enums\UserRole;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => redirect()->route('login'));
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'tenant.access'])->group(function (): void {
    Route::get('/dashboard', fn (Request $request) => redirect()->route($request->user()->role->dashboard()))->name('dashboard');
    foreach (UserRole::cases() as $role) {
        Route::get('/'.($role === UserRole::SuperAdmin ? 'super-admin' : $role->value).'/dashboard', fn () => view('dashboard'))
            ->middleware('can:dashboard.'.$role->value)->name($role->dashboard());
    }
});
Route::get('/branding/logo', function () {
    $manager = app(TenantManager::class);
    $path = $manager->current()?->settings['logo_path'] ?? null;
    abort_unless(is_string($path) && str_starts_with($path, $manager->path().'/') && ! str_contains($path, '..') && ! str_contains($path, '\\'), 404);
    abort_unless(Storage::disk('local')->exists($path), 404);

    return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
})->name('tenant.logo');
