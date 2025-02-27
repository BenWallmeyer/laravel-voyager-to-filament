<?php

namespace VoyagerToFilament\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ExportVoyagerToFilament extends Command
{
    protected $signature = 'voyager-to-filament:export';
    protected $description = 'Exportiere Voyager-Modelle und Migrationen für Filament';

    public function handle()
    {
        $exportPath = storage_path('voyager_to_filament');
        $filamentModelsPath = $exportPath . '/app/Models';
        $migrationPath = $exportPath . '/database/migrations';

        File::deleteDirectory($exportPath);
        File::makeDirectory($filamentModelsPath, 0755, true);
        File::makeDirectory($migrationPath, 0755, true);

        // Modelle aus app/ nach app/Models verschieben
        $voyagerModelsPath = app_path();
        $modelFiles = File::files($voyagerModelsPath);

     foreach ($modelFiles as $file) {
    if ($file->getFilename() === 'User.php') {
        $this->info("Überspringe User.php...");
        continue; // Ignoriere die Datei
    }

            $fileName = $file->getFilename();
            $filePath = $file->getPathname();
            $newFilePath = $filamentModelsPath . '/' . $fileName;

            // Namespace anpassen
            $content = File::get($filePath);
            $updatedContent = str_replace('namespace App;', 'namespace App\Models;', $content);
            File::put($newFilePath, $updatedContent);

            $this->info("Model exportiert: $fileName");
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
