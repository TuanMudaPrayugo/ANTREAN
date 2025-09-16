<?php

use App\Http\Middleware\NoCache;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    KStepController,
    KIssueController,
    AntreanController,
    KLayananController,
    TimelineController,
    AnalyticsController,
    DashboardController,
    TanyaSaharController,
    KategoriIssueController,
    SyaratDokumenController,
    KonfirmasiPetugasController
};

/*
|--------------------------------------------------------------------------
| Public / basic
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => view('welcome'));
Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Master Data
|--------------------------------------------------------------------------
*/
// KLayanan
Route::prefix('KLayanan')->group(function () {
    Route::get('/',               [KLayananController::class,'index'])->name('KLayanan');
    Route::get('/create',         [KLayananController::class,'create'])->name('KLayananCreate');
    Route::post('/store',         [KLayananController::class,'store'])->name('KLayananStore');
    Route::get('/edit/{id}',      [KLayananController::class,'edit'])->name('KLayananEdit');
    Route::post('/update/{id}',   [KLayananController::class,'update'])->name('KLayananUpdate');
    Route::get('/destroy/{id}',   [KLayananController::class,'destroy'])->name('KLayananDestroy');
});

// KStep
Route::prefix('KStep')->group(function () {
    Route::get('/',               [KStepController::class,'index'])->name('KStep');
    Route::get('/create',         [KStepController::class,'create'])->name('KStepCreate');
    Route::post('/store',         [KStepController::class,'store'])->name('KStepStore');
    Route::get('/edit/{id}',      [KStepController::class,'edit'])->name('KStepEdit');
    Route::post('/update/{id}',   [KStepController::class,'update'])->name('KStepUpdate');
    Route::get('/destroy/{id}',   [KStepController::class,'destroy'])->name('KStepDestroy');
    // Ajax helper
    Route::get('/ajax/steps-by-layanan', [KStepController::class,'byLayanan'])->name('steps.byLayanan');
});

// KIssue
Route::prefix('KIssue')->group(function () {
    Route::get('/create',         [KIssueController::class,'create'])->name('KIssueCreate');
    Route::post('/store',         [KIssueController::class,'store'])->name('KIssueStore');
    Route::get('/edit/{id}',      [KIssueController::class,'edit'])->name('KIssueEdit');
    Route::post('/update/{id}',   [KIssueController::class,'update'])->name('KIssueUpdate');
    Route::get('/destroy/{id}',   [KIssueController::class,'destroy'])->name('KIssueDestroy');
});

// Kategori Issue
Route::prefix('KategoriIssue')->group(function () {
    Route::get('/',               [KategoriIssueController::class,'index'])->name('KategoriIssue');
    Route::get('/create',         [KategoriIssueController::class,'create'])->name('KategoriIssueCreate');
    Route::post('/store',         [KategoriIssueController::class,'store'])->name('KategoriIssueStore');
    Route::get('/edit/{id}',      [KategoriIssueController::class,'edit'])->name('KategoriIssueEdit');
    Route::post('/update/{id}',   [KategoriIssueController::class,'update'])->name('KategoriIssueUpdate');
    Route::get('/destroy/{id}',   [KategoriIssueController::class,'destroy'])->name('KategoriIssueDestroy');
});

// Syarat Dokumen
Route::prefix('SyaratDokumen')->group(function () {
    Route::get('/',               [SyaratDokumenController::class,'index'])->name('SyaratDokumen');
    Route::get('/create',         [SyaratDokumenController::class,'create'])->name('SyaratDokumenCreate');
    Route::post('/store',         [SyaratDokumenController::class,'store'])->name('SyaratDokumenStore');
    Route::get('/edit/{id}',      [SyaratDokumenController::class,'edit'])->name('SyaratDokumenEdit');
    Route::post('/update/{id}',   [SyaratDokumenController::class,'update'])->name('SyaratDokumenUpdate');
    Route::get('/destroy/{id}',   [SyaratDokumenController::class,'destroy'])->name('SyaratDokumenDestroy');
});

// Tanya Sahar
Route::prefix('TanyaSahar')->group(function () {
    Route::get('/',               [TanyaSaharController::class,'index'])->name('TanyaSahar');
    Route::post('/ask',           [TanyaSaharController::class,'ask'])->name('TanyaSahar.ask');
    Route::get('/ask', fn() => redirect()->route('TanyaSahar'));
    Route::post('/feedback',      [TanyaSaharController::class,'feedback'])->name('TanyaSahar.feedback');
});

/*
|--------------------------------------------------------------------------
| Antrean (legacy & index)
|--------------------------------------------------------------------------
*/
Route::get('Antrean', [AntreanController::class,'index'])->name('Antrean'); // alias lama
Route::post('/progress/{progress}/decision', [AntreanController::class,'decision'])->name('progress.decision'); // legacy (opsional)

/*
|--------------------------------------------------------------------------
| Group with NoCache (UI utama)
|--------------------------------------------------------------------------
*/
Route::middleware(['web', NoCache::class])->group(function () {
    // Antrean UI
    Route::get('/antrean',                [AntreanController::class,'index'])->name('antrean.index');

    // Start via QR modern (gunakan ini; hapus rute versi lama /layanan/{layanan}/scan agar tidak duplikat)
    Route::get('/scan/{layanan}',         [AntreanController::class,'scan'])->name('scan.show');
    Route::post('/start/{layanan}',       [AntreanController::class,'start'])->name('scan.start');

    // Timeline page + data (INI SATU-SATUNYA timeline.data)
    Route::get('/tiket/{tiket}/timeline',         [TimelineController::class,'index'])->name('timeline.show');
    Route::get('/tiket/{ticket}/timeline/data',   [TimelineController::class,'data'])->name('timeline.data');

    // Magic link + PIN
    Route::get('/join/{ticket}',          [TimelineController::class,'joinForm'])->name('timeline.join');
    Route::post('/join/{ticket}',         [TimelineController::class,'joinVerify'])->name('timeline.join.verify');
    Route::post('/timeline/{ticket}/fp', [TimelineController::class,'storeFingerprint'])->name('timeline.store-fp');

    // Petugas
    Route::get('/petugas',                        [KonfirmasiPetugasController::class,'index'])->name('petugas.index');
    Route::get('/petugas/step/{step}/issues',     [KonfirmasiPetugasController::class,'issues'])->name('petugas.step.issues');
    Route::get('/petugas/step/{step}/issues/frequent', [KonfirmasiPetugasController::class,'frequentIssues'])->name('petugas.step.issues.frequent'); // optional (fallback)
    Route::get('/petugas/issues/{issue}/detail',  [KonfirmasiPetugasController::class,'issueDetail'])->name('petugas.issue.detail');
    Route::post('/petugas/progress/{progress}/action', [KonfirmasiPetugasController::class,'action'])->name('petugas.progress.action');

    // Analytics (Top Issues) – jika kamu butuh endpoint umum
    Route::get('/api/analytics/frequent-issues',  [AnalyticsController::class,'frequentIssues'])->name('api.analytics.frequent-issues');
});
