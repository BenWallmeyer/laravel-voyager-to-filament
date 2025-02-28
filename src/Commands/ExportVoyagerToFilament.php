<?php

namespace VoyagerToFilament\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ExportVoyagerToFilament extends Command
{
    protected $signature = 'voyager-to-filament:export';
    protected $description = 'Exportiere Voyager-Modelle, Migrationen, Controller und Traits für Filament';

    public function handle()
    {
        $exportPath = storage_path('voyager_to_filament');
        $filamentModelsPath = $exportPath . '/app/Models';
        $controllerPath = $exportPath . '/app/Http/Controllers';
        $traitPath = $exportPath . '/app/Traits';
        $migrationPath = $exportPath . '/database/migrations';

        File::deleteDirectory($exportPath);
        File::makeDirectory($filamentModelsPath, 0755, true);
        File::makeDirectory($controllerPath, 0755, true);
        File::makeDirectory($traitPath, 0755, true);
        File::makeDirectory($migrationPath, 0755, true);

        // Modelle exportieren
        $voyagerModelsPaths = [app_path(), app_path('Models')];
        foreach ($voyagerModelsPaths as $voyagerModelsPath) {
            if (!File::exists($voyagerModelsPath)) {
                continue;
            }

            $modelFiles = File::files($voyagerModelsPath);
            foreach ($modelFiles as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                if ($file->getFilename() === 'User.php') {
                    $this->info("Überspringe User.php...");
                    continue; // Ignoriere die Datei
                }

                $fileName = $file->getFilename();
                $filePath = $file->getPathname();
                $newFilePath = $filamentModelsPath . '/' . $fileName;

                // Model übernehmen und Namespace anpassen, falls nötig
                $content = File::get($filePath);
                $updatedContent = str_replace(
                    ['namespace App;', 'namespace App\\Models\\Http\\'],
                    'namespace App\\Models;',
                    $content
                );
                File::put($newFilePath, $updatedContent);
                $this->info("Model exportiert: $fileName");
            }
        }

        // Controller exportieren
        $controllerFiles = File::files(app_path('Http/Controllers'));
        foreach ($controllerFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $fileName = $file->getFilename();
            $filePath = $file->getPathname();
            $newFilePath = $controllerPath . '/' . $fileName;

            // Controller-Inhalt übernehmen und Model-Import anpassen
            $content = File::get($filePath);
            $updatedContent = str_replace(
                ['use App\\Models\\Http\\Controllers\\', 'use App\\Models\\Http\\Traits\\'],
                ['use App\\Http\\Controllers\\', 'use App\\Traits\\'],
                $content
            );
            File::put($newFilePath, $updatedContent);
            $this->info("Controller exportiert und Model-Import angepasst: $fileName");
        }

        // Traits exportieren
        if (File::exists(app_path('Traits'))) {
            $traitFiles = File::files(app_path('Traits'));
            foreach ($traitFiles as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $fileName = $file->getFilename();
                $filePath = $file->getPathname();
                $newFilePath = $traitPath . '/' . $fileName;
                File::copy($filePath, $newFilePath);
                $this->info("Trait exportiert: $fileName");
            }
        }

        // Migrationen generieren
        $this->info("Erstelle Migrationen...");
        $this->call('migrate:generate');

        // Migrationen kopieren
        $generatedMigrations = File::files(database_path('migrations'));
        foreach ($generatedMigrations as $migrationFile) {
            File::copy($migrationFile->getPathname(), $migrationPath . '/' . $migrationFile->getFilename());
        }

        // ZIP-Datei erstellen
        $zipFile = storage_path('voyager_to_filament.zip');
        $this->zipDirectory($exportPath, $zipFile);
        $this->info("Export abgeschlossen! ZIP gespeichert unter: $zipFile");
    }

    private function zipDirectory($folder, $zipFile)
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $relativePath = substr($file->getPathname(), strlen($folder) + 1);
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }

        return $zip->close();
    }
}
