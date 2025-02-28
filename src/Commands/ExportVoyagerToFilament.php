<?php

namespace VoyagerToFilament\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ExportVoyagerToFilament extends Command
{
    protected $signature = 'voyager-to-filament:export';
    protected $description = 'Exportiere Voyager-Modelle, Migrationen und Controller für Filament';

    public function handle()
    {
        $exportPath = storage_path('voyager_to_filament');
        $filamentModelsPath = $exportPath . '/app/Models';
        $controllerPath = $exportPath . '/app/Http/Controllers';
        $migrationPath = $exportPath . '/database/migrations';

        File::deleteDirectory($exportPath);
        File::makeDirectory($filamentModelsPath, 0755, true);
        File::makeDirectory($controllerPath, 0755, true);
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

                // Model übernehmen, ohne Namespace-Anpassung
                File::copy($filePath, $newFilePath);
                $this->info("Model exportiert: $fileName");

                // Controller übernehmen und Model-Import anpassen
                $originalControllerPath = app_path('Http/Controllers/' . $fileName);
                $newControllerPath = $controllerPath . '/' . $fileName;
                
                if (File::exists($originalControllerPath)) {
                    $controllerContent = File::get($originalControllerPath);
                    $updatedControllerContent = str_replace(
                        ['use App\\', 'use App\\Models\\'],
                        'use App\\Models\\',
                        $controllerContent
                    );
                    File::put($newControllerPath, $updatedControllerContent);
                    $this->info("Controller exportiert und Model-Import angepasst: $fileName");
                }
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
