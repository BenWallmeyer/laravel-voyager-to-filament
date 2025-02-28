<?php

namespace VoyagerToFilament\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ImportVoyagerToFilament extends Command
{
    protected $signature = 'voyager-to-filament:import {zipPath?}';
    protected $description = 'Importiere exportierte Voyager-Modelle, Migrationen, Controller und Traits für Filament';

    public function handle()
    {
        $zipPath = $this->argument('zipPath') ?? storage_path('voyager_to_filament.zip');
        if (!file_exists($zipPath)) {
            $this->error("ZIP-Datei nicht gefunden: $zipPath");
            return;
        }

        // ZIP entpacken
        $importPath = storage_path('voyager_to_filament_import');
        File::deleteDirectory($importPath);
        File::makeDirectory($importPath, 0755, true);
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($importPath);
            $zip->close();
        } else {
            $this->error("Fehler beim Entpacken der ZIP-Datei!");
            return;
        }

        // Modelle, Controller und Traits importieren
        $this->importDirectory($importPath . '/app/Models', app_path('Models'));
        $this->importDirectory($importPath . '/app/Http/Controllers', app_path('Http/Controllers'));
        $this->importDirectory($importPath . '/app/Traits', app_path('Traits'));
        $this->importDirectory($importPath . '/app/Http/Traits', app_path('Http/Traits'));
        $this->importDirectory($importPath . '/app/Http/Controllers/Traits', app_path('Http/Controllers/Traits'));
        $this->importDirectory($importPath . '/app/Models/Traits', app_path('Models/Traits'));
        
        // Migrationen importieren und ausführen
        $this->importDirectory($importPath . '/database/migrations', database_path('migrations'));
        $this->info("Führe Migrationen aus...");
        $this->call('migrate');

        // Filament-Resources für importierte Modelle erstellen
        $this->generateFilamentResources(app_path('Models'));

        $this->info("Import erfolgreich abgeschlossen!");
    }

    private function importDirectory($source, $destination)
    {
        if (File::exists($source)) {
            File::copyDirectory($source, $destination);
            $this->info("Importiert: " . basename($source));
        }
    }

    private function generateFilamentResources($modelPath)
    {
        $modelFiles = File::files($modelPath);
        foreach ($modelFiles as $file) {
            $modelName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $this->call('make:filament-resource', ['name' => $modelName]);
        }
        $this->info("Filament-Resources generiert.");
    }
}
