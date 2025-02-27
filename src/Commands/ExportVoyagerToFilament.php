<?php

namespace VoyagerToFilament\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ExportVoyagerToFilament extends Command
{
    protected $signature = 'export:voyager-to-filament';
    protected $description = 'Exportiere Voyager-Modelle für Filament als ZIP';

    public function handle()
    {
        $exportPath = storage_path('voyager_to_filament');
        $filamentModelsPath = $exportPath . '/app/Models';
        $filamentResourcesPath = $exportPath . '/app/Filament/Resources';
        $migrationPath = $exportPath . '/database/migrations';

        File::deleteDirectory($exportPath);
        File::makeDirectory($filamentModelsPath, 0755, true);
        File::makeDirectory($filamentResourcesPath, 0755, true);
        File::makeDirectory($migrationPath, 0755, true);

        $voyagerModelsPath = app_path();
        $modelFiles = File::files($voyagerModelsPath);

        foreach ($modelFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $fileName = $file->getFilename();
            $filePath = $file->getPathname();
            $newFilePath = $filamentModelsPath . '/' . $fileName;

            $content = File::get($filePath);
            $updatedContent = str_replace('namespace App;', 'namespace App\Models;', $content);

            File::put($newFilePath, $updatedContent);
            $this->info("Model exportiert: $fileName");

            $modelClassName = pathinfo($fileName, PATHINFO_FILENAME);
            $this->call('make:filament-resource', ['name' => $modelClassName]);

            $generatedResourcePath = app_path("Filament/Resources/{$modelClassName}Resource.php");
            if (File::exists($generatedResourcePath)) {
                File::move($generatedResourcePath, $filamentResourcesPath . "/{$modelClassName}Resource.php");
            }
        }

        $this->info("Erstelle Migrationen...");
        $this->call('migrate:generate');

        $generatedMigrations = File::files(database_path('migrations'));
        foreach ($generatedMigrations as $migrationFile) {
            File::copy($migrationFile->getPathname(), $migrationPath . '/' . $migrationFile->getFilename());
        }

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