<?php

use App\NutsAndBolts\MediaCache;
use App\Route;
use App\Runner\Controllers\APODController;
use App\Services\APOD\APODService;
use App\Runner\Controllers\KeyController;
use App\Services\Settings\KeyService;
use App\Runner\Controllers\DONKIController;
use App\Services\DONKI\DONKIService;
use App\Runner\Controllers\EPICController;
use App\Services\EPIC\EPICService;
use App\Runner\Controllers\GIBSController;
use App\Services\GIBS\GIBSService;
use App\Runner\Controllers\EONETController;
use App\Services\EONET\EONETService;
use App\Runner\Controllers\NeoWsController;
use App\Services\NeoWs\NeoWsService;
use App\Runner\Controllers\InSightController;
use App\Services\InSight\InSightService;
use App\Runner\Controllers\TLEController;
use App\Services\TLE\TLEService;
use App\Runner\Controllers\TechTransferController;
use App\Services\TechTransfer\TechTransferService;
use App\Runner\Controllers\ImagesController;
use App\Services\Images\ImagesService;
use App\Runner\Controllers\SettingsController;
use App\Services\Settings\SettingsService;

Route::controller(APODController::class, function ($app, array $args) {
    [$window] = $args;
    $service = new APODService();

    return new APODController($window, $service);
});

Route::controller(EPICController::class, function ($app, array $args) {
    [$window] = $args;

    return new EPICController($window, new EPICService());
});

Route::controller(DONKIController::class, function ($app, array $args) {
    [$window] = $args;

    return new DONKIController($window, new DONKIService());
});

Route::controller(ImagesController::class, function ($app, array $args) {
    [$window] = $args;

    return new ImagesController($window, new ImagesService());
});

Route::controller(EONETController::class, function ($app, array $args) {
    [$window] = $args;

    return new EONETController($window, new EONETService());
});

Route::controller(NeoWsController::class, function ($app, array $args) {
    [$window] = $args;

    return new NeoWsController($window, new NeoWsService());
});

Route::controller(InSightController::class, function ($app, array $args) {
    [$window] = $args;

    return new InSightController($window, new InSightService());
});

Route::controller(TLEController::class, function ($app, array $args) {
    [$window] = $args;

    return new TLEController($window, new TLEService());
});

Route::controller(TechTransferController::class, function ($app, array $args) {
    [$window] = $args;

    return new TechTransferController($window, new TechTransferService());
});

Route::controller(GIBSController::class, function ($app, array $args) {
    [$window] = $args;

    return new GIBSController($window, new GIBSService());
});

Route::controller(KeyController::class, function ($app, array $args) {
    [$window] = $args;

    return new KeyController($window, new KeyService());
});

Route::controller(SettingsController::class, function ($app, array $args) {
    [$window] = $args;

    return new SettingsController($window, new SettingsService());
});
