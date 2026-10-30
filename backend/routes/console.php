<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| La importación de resultados de LIMS Labware es periódica y no requiere
| intervención manual: se programa sólo cuando la integración está habilitada
| (véase D7).
|
*/

if (config('lims.enabled')) {
    Schedule::command('lims:sync')
        ->hourly()
        ->withoutOverlapping();
}

/*
| La evaluación de la monitorización es automática: el comando recorre las
| lecturas que publica la planta y genera las alertas de prioridad máxima sin
| que ninguna persona tenga que intervenir. Se programa sólo cuando hay un
| fichero de lecturas configurado.
*/

if (config('monitoring.feed.path')) {
    Schedule::command('alerts:monitor')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}
