<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:mvp', function (): void {
    $this->info('Lab MVP backend is installed.');
})->purpose('Show Lab MVP installation status');
