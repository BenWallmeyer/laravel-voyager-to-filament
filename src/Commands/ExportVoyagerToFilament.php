<?php

namespace VoyagerToFilament\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ExportVoyagerToFilament extends Command
{
    protected $signature = 'voyager-to-filament:export';
    protected $description = 'Exportiert Voyager-Modelle, Migrationen, Controller und Traits für Filament';

    public function handle()
    {
        $exportPath = storage_path('voyager_to_filament_export');
        File::deleteDirectory($exportPath);
        File::makeDirectory($exportPath, 0755, true);
        
        // Modelle, Controller und Traits exportieren
        $this->exportDirectory(app_path('Models'), "$exportPath/app/Models");
        $this->exportDirectory(app_path('Http/Controllers'), "$exportPath/app/Http/Controllers");
        $this->exportDirectory(app_path('Traits'), "$exportPath/app/Traits");
        $this->exportDirectory(app_path('Http/Traits'), "$exportPath/app/Http/Traits");
        $this->exportDirectory(app_path('Http/Controllers/Traits'), "$exportPath/app/Http/Controllers/Traits");
        $this->exportDirectory(app_path('Models/Traits'), "$exportPath/app/Models/Traits");
        
        // Migrationen exportieren mit Filter
        $this->exportMigrations(database_path('migrations'), "$exportPath/database/migrations");
        
        // ZIP-Datei erstellen
        $this->createZip($exportPath);

        $this->info("Export abgeschlossen! ZIP-Datei gespeichert in: " . storage_path('voyager_to_filament.zip'));
    }

    private function exportDirectory($source, $destination)
    {
        if (File::exists($source)) {
            File::copyDirectory($source, $destination);
            $this->info("Exportiert: " . basename($source));
        }
    }

    private function exportMigrations($source, $destination)
    {
        if (File::exists($source)) {
            $ignoredTables = [
                'data_rows', 'data_types', 'failed_jobs', 'menus', 'menu_items',
                'migrations', 'password_reset_tokens', 'permissions', 'permission_role',
                'personal_access_tokens', 'roles', 'settings', 'translations', 'users', 'user_roles'
            ];

            $migrationFiles = File::files($source);
            foreach ($migrationFiles as $file) {
                $fileName = $file->getFilename();
                
                // Prüfen, ob die Migration eine unerwünschte Tabelle enthält
                foreach ($ignoredTables as $ignoredTable) {
                    if (str_contains($fileName, $ignoredTable)) {
                        $this->warn("Überspringe Export von Migration: $fileName");
                        continue 2; // Zur nächsten Migration springen
                    }
                }
                
                File::copy($file->getPathname(), "$destination/$fileName");
                $this->info("Migration exportiert: $fileName");
            }
        }
    }

    private function createZip($folder)
    {
        $zipFile = storage_path('voyager_to_filament.zip');
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder), \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $relativePath = substr($file->getPathname(), strlen($folder) + 1);
                    $zip->addFile($file->getPathname(), $relativePath);
                }
            }
            $zip->close();
        }
    }
}
