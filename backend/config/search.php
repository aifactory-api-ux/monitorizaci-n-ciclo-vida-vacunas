<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuración de la búsqueda avanzada de lotes
|--------------------------------------------------------------------------
|
| La búsqueda cachea la página de resultados en el almacén de caché de la
| aplicación, que en el despliegue es Redis. La clave de cada entrada es el
| hash de los filtros solicitados, de modo que dos peticiones con los mismos
| criterios reutilizan la misma entrada (véase D14).
|
| La invalidación es por generación: cuando cambia el estado de un lote se
| incrementa el contador de generación y las entradas anteriores dejan de
| ser alcanzables, sin recorrer las claves almacenadas.
|
*/

return [
    'cache' => [
        /*
         | Almacén de caché de los resultados de búsqueda.
         */
        'store' => env('SEARCH_CACHE_STORE', 'redis'),

        /*
         | Prefijo de las claves de la caché de búsqueda.
         */
        'prefix' => env('SEARCH_CACHE_PREFIX', 'vaccine_batch_search'),

        /*
         | Tiempo de vida, en segundos, de una entrada de la caché.
         */
        'ttl_seconds' => (int) env('SEARCH_CACHE_TTL', 300),
    ],
];
