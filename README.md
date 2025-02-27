# Laravel Voyager to Filament Exporter

Dieses Laravel-Plugin exportiert alle Voyager-Modelle als ZIP für Filament.

## Installation

1. Füge das Repository in deiner `composer.json` hinzu:

```
composer config repositories.voyager-to-filament path ./packages/laravel-voyager-to-filament
```

2. Installiere das Plugin:

```
composer require deinname/laravel-voyager-to-filament
```

3. Führe den Export aus:

```
php artisan export:voyager-to-filament
```

Das ZIP befindet sich in `storage/voyager_to_filament.zip`.
