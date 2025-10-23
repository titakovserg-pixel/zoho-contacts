<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('zoho')->group(function() {
   Route::get('/token', [\App\Http\Controllers\ZohoController::class, 'token'])->name('zoho.token');   
   Route::get('/test', [\App\Http\Controllers\ZohoController::class, 'test'])->name('zoho.test');
});
Route::get('/contacts', [\App\Http\Controllers\EntityController::class, 'contactList'])->name('contacts');
Route::get('/contact/add', [\App\Http\Controllers\EntityController::class, 'contactAdd'])->name('contact.add');
Route::post('/contact/create', [\App\Http\Controllers\EntityController::class, 'contactCreate'])->name('contact.create');
Route::get('/contact/{id}', [\App\Http\Controllers\EntityController::class, 'contactEdit'])->name('contact.edit');
Route::put('/contact/{id}', [\App\Http\Controllers\EntityController::class, 'contactUpdate'])->name('contact.update');
Route::delete('/contact/{id}', [\App\Http\Controllers\EntityController::class, 'contactRemove'])->name('contact.remove');

require __DIR__.'/auth.php';
