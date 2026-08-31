<?php

use App\Http\Controllers\Api\OperatorPrescriptionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\AiRequestLogController;
use Illuminate\Support\Facades\Route;

Route::post('/prescriptions', [PrescriptionController::class, 'store']);
Route::get('/prescriptions/{trackingNumber}', [PrescriptionController::class, 'show']);
Route::get('/prescriptions/{trackingNumber}/invoice', [PrescriptionController::class, 'invoice']);
Route::post('/prescriptions/{trackingNumber}/payments', [PaymentController::class, 'store']);

Route::prefix('operator')->group(function () {
    Route::get('/prescriptions', [OperatorPrescriptionController::class, 'index']);
    Route::get('/prescriptions/{prescription}', [OperatorPrescriptionController::class, 'show']);
    Route::put('/prescriptions/{prescription}/items', [OperatorPrescriptionController::class, 'updateItems']);
    Route::post('/prescriptions/{prescription}/confirm', [OperatorPrescriptionController::class, 'confirm']);
    Route::get('/ai-logs', [AiRequestLogController::class, 'index']);
    Route::get('/ai-logs/summary', [AiRequestLogController::class, 'summary']);
});
