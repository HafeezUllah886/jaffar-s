<?php

use App\Http\Controllers\FbrController;
use App\Http\Controllers\SalePaymentsController;
use App\Http\Controllers\SalesController;
use App\Http\Middleware\adminCheck;
use App\Http\Middleware\confirmPassword;
use App\Models\products;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    Route::get('sales/getproduct/{id}', [SalesController::class, 'getSignleProduct']);
    Route::get('sales/getcustomer/{id}', [SalesController::class, 'getCustomer']);
    Route::get('sales/getfreshprice/{id}', function ($id) {
        return json_encode(products::find($id)->price);
    });

});
Route::middleware('auth', adminCheck::class)->group(function () {

    Route::resource('sale', SalesController::class);

    Route::get('sales/delete/{id}', [SalesController::class, 'destroy'])->name('sale.delete')->middleware(confirmPassword::class);
    Route::get('sales/printall/{start}/{end}', [SalesController::class, 'printAll'])->name('sale.printall');
    Route::get('sales/gatepass/{id}', [SalesController::class, 'gatePass'])->name('sale.gatePass');
    Route::get('product/searchByCode/{code}', [SalesController::class, 'getProductByCode'])->name('product.searchByCode');

    Route::get('salepayment/{id}', [SalePaymentsController::class, 'index'])->name('salePayment.index');
    Route::get('salepayment/show/{id}', [SalePaymentsController::class, 'show'])->name('salePayment.show');
    Route::get('salepayment/delete/{id}/{ref}', [SalePaymentsController::class, 'destroy'])->name('salePayment.delete')->middleware(confirmPassword::class);
    Route::resource('sale_payment', SalePaymentsController::class);

    // FBR Digital Invoicing Integration Routes
    Route::get('settings/fbr', [FbrController::class, 'settings'])->name('settings.fbr');
    Route::post('settings/fbr', [FbrController::class, 'updateSettings'])->name('settings.fbr.update');
    Route::post('settings/fbr/test', [FbrController::class, 'testConnection'])->name('settings.fbr.test');
    Route::post('sales/{id}/send-to-fbr', [FbrController::class, 'sendInvoice'])->name('sale.sendToFbr');
    Route::get('sales/{id}/fbr-error', [FbrController::class, 'viewError'])->name('sale.fbrError');
    Route::get('sales/{id}/fbr-preview', [FbrController::class, 'previewPayload'])->name('sale.fbrPreview');

});
