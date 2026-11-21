<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingRequestController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CheckOutController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDocumentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositSettlementController;
use App\Http\Controllers\InspectionFileController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KitController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MaintenancePlanController;
use App\Http\Controllers\MaintenanceTaskController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductLabelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\RentalContractController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\RentalItemController;
use App\Http\Controllers\RentalKitController;
use App\Http\Controllers\RentalTransitionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitScanController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('locale', LocaleController::class)->name('locale');

Route::prefix('install')->name('install.')->middleware('not.installed')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('database', [InstallController::class, 'database'])->name('database');
    Route::post('database', [InstallController::class, 'saveDatabase'])->name('database.save');
    Route::get('administrator', [InstallController::class, 'administrator'])->name('administrator');
    Route::post('administrator', [InstallController::class, 'install'])->name('administrator.save');
});

Route::redirect('/', '/dashboard');

Route::middleware('public.booking')->group(function () {
    Route::get('book', [PublicBookingController::class, 'create'])->name('book.create');
    Route::post('book', [PublicBookingController::class, 'store'])->middleware('throttle:booking')->name('book.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('languages/{language}/export', [LanguageController::class, 'export'])->name('languages.export');
    Route::resource('languages', LanguageController::class)->except('show');
    Route::resource('users', UserController::class)->except('show');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export/{report}', [ReportController::class, 'export'])->name('reports.export');

    Route::get('booking-requests', [BookingRequestController::class, 'index'])->name('booking-requests.index');
    Route::post('booking-requests/{booking}/convert', [BookingRequestController::class, 'convert'])->name('booking-requests.convert');
    Route::post('booking-requests/{booking}/reject', [BookingRequestController::class, 'reject'])->name('booking-requests.reject');

    Route::get('availability', AvailabilityController::class)->name('availability');
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('calendar/events', [CalendarController::class, 'events'])->name('calendar.events');

    Route::resource('customers', CustomerController::class);
    Route::resource('customers.documents', CustomerDocumentController::class)
        ->shallow()
        ->only(['store', 'show', 'update', 'destroy']);

    Route::resource('rentals', RentalController::class)->except('destroy');
    Route::resource('rentals.items', RentalItemController::class)
        ->shallow()
        ->only(['store', 'destroy']);
    Route::post('rentals/{rental}/confirm', [RentalTransitionController::class, 'confirm'])->name('rentals.confirm');
    Route::post('rentals/{rental}/cancel', [RentalTransitionController::class, 'cancel'])->name('rentals.cancel');
    Route::get('rentals/{rental}/contract', RentalContractController::class)->name('rentals.contract');

    Route::get('rentals/{rental}/check-out', [CheckOutController::class, 'create'])->name('rentals.check-out');
    Route::post('rentals/{rental}/check-out', [CheckOutController::class, 'store'])->name('rentals.check-out.store');
    Route::get('rentals/{rental}/check-in', [CheckInController::class, 'create'])->name('rentals.check-in');
    Route::post('rentals/{rental}/check-in', [CheckInController::class, 'store'])->name('rentals.check-in.store');
    Route::post('rentals/{rental}/settle', [DepositSettlementController::class, 'store'])->name('rentals.settle');
    Route::get('inspection-photos/{photo}', [InspectionFileController::class, 'photo'])->name('inspection-photos.show');
    Route::get('inspections/{inspection}/signature', [InspectionFileController::class, 'signature'])->name('inspections.signature');
    Route::get('scan', ScanController::class)->name('scan');

    Route::resource('invoices', InvoiceController::class)->only(['index', 'show', 'edit', 'update']);
    Route::post('rentals/{rental}/invoices', [InvoiceController::class, 'store'])->name('rentals.invoices.store');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('invoices.paid');

    Route::resource('kits', KitController::class)->except('show');
    Route::post('rentals/{rental}/kits', [RentalKitController::class, 'store'])->name('rentals.kits.store');
    Route::delete('rentals/{rental}/kits/{kit}', [RentalKitController::class, 'destroy'])->name('rentals.kits.destroy');

    Route::get('maintenance', [MaintenanceTaskController::class, 'index'])->name('maintenance.index');
    Route::post('units/{unit}/maintenance', [MaintenanceTaskController::class, 'store'])->name('units.maintenance.store');
    Route::post('maintenance/{task}/complete', [MaintenanceTaskController::class, 'complete'])->name('maintenance.complete');
    Route::post('maintenance/{task}/cancel', [MaintenanceTaskController::class, 'cancel'])->name('maintenance.cancel');
    Route::put('products/{product}/maintenance-plan', [MaintenancePlanController::class, 'update'])->name('products.maintenance-plan.update');
    Route::delete('products/{product}/maintenance-plan', [MaintenancePlanController::class, 'destroy'])->name('products.maintenance-plan.destroy');

    Route::get('transfers', [StockTransferController::class, 'index'])->name('transfers.index');
    Route::get('transfers/create', [StockTransferController::class, 'create'])->name('transfers.create');
    Route::post('transfers', [StockTransferController::class, 'store'])->name('transfers.store');
    Route::post('transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('transfers.receive');
    Route::post('transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('transfers.cancel');

    Route::resource('locations', LocationController::class)->except('show');
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('brands', BrandController::class)->except('show');
    Route::resource('products', ProductController::class);

    Route::resource('products.units', UnitController::class)
        ->shallow()
        ->except('index');
    Route::get('products/{product}/labels', ProductLabelController::class)->name('products.labels');
    Route::get('units/scan/{unit:qr_code}', UnitScanController::class)->name('units.scan');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
