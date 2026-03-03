<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Open\WebhookController;

Route::post('webhooks/{provider}', [WebhookController::class, 'handle']);

