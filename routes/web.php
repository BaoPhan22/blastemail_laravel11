<?php

use App\Http\Controllers\BlastEmailController;
use App\Models\BlastEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     $emails = BlastEmail::select('email', DB::raw('count(*) as occurrences'))
//         ->groupBy('email')
//         ->get();
//     return view('welcome', ['emails' => $emails]);
// });

Route::post('/blastemail', [BlastEmailController::class, 'blastemail'])->name('blastemail');
Route::get('/', [BlastEmailController::class, 'index'])->name('index');

Route::post('/stop-crawling', function () {
    DB::table('control_flags')->where('name', 'crawling_active')->update(['active' => false]);
    return redirect()->route('index')->with('status', 'Crawling has been stopped.');
})->name('stop-crawling');