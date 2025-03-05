<?php

namespace VoyagerToFilament\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use ZipArchive;

class ImportVoyagerToFilament extends Command
{
    protected $signature = 'voyager-to-filament:import {zipPath?}';
    protected $description = 'Importiere exportierte Voyager-Modelle, Migrationen, Controller, Traits und seedet die Datenbank falls nötig';

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
        $this->importDirectory($importPath . '/app/Models', app_path('Models'), true);
        $this->importDirectory($importPath . '/app/Http/Controllers', app_path('Http/Controllers'));
        $this->importDirectory($importPath . '/app/Traits', app_path('Traits'));
        $this->importDirectory($importPath . '/app/Http/Traits', app_path('Http/Traits'));
        $this->importDirectory($importPath . '/app/Http/Controllers/Traits', app_path('Http/Controllers/Traits'));
        $this->importDirectory($importPath . '/app/Models/Traits', app_path('Models/Traits'));
        
        // Migrationen importieren und ausführen
        $this->importMigrations($importPath . '/database/migrations');
        
        // Filament-Resources für importierte Modelle erstellen
        $this->generateFilamentResources(app_path('Models'));

        // Seed-Datenbank falls Modelle existieren
        $this->seedDatabase(app_path('Models'));

        $this->info("Import erfolgreich abgeschlossen!");
    }

    private function importDirectory($source, $destination, $modifyModels = false)
    {
        if (File::exists($source)) {
            File::copyDirectory($source, $destination);
            $this->info("Importiert: " . basename($source));
            
            if ($modifyModels) {
                $this->addFillableAttributes($destination);
            }
        }
    }

    private function importMigrations($migrationPath)
    {
        if (File::exists($migrationPath)) {
            $migrationFiles = File::files($migrationPath);
            foreach ($migrationFiles as $file) {
                $fileName = $file->getFilename();
                $filePath = $file->getPathname();
                $destinationPath = database_path('migrations/' . $fileName);
                
                if (!File::exists($destinationPath)) {
                    File::copy($filePath, $destinationPath);
                    $this->info("Migration importiert: $fileName");
                }
            }
            
            $this->info("Führe Migrationen aus...");
            $errors = [];
            foreach ($migrationFiles as $file) {
                try {
                    $this->call('migrate', ['--force' => true, '--path' => 'database/migrations/' . $file->getFilename()]);
                } catch (\Exception $e) {
                    $errors[] = "Fehler in Migration {$file->getFilename()}: " . $e->getMessage();
                }
            }
            
            if (!empty($errors)) {
                $this->error("Folgende Migrationen sind fehlgeschlagen:");
                foreach ($errors as $error) {
                    $this->error($error);
                }
            }
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

    private function seedDatabase($modelPath)
    {
        $modelFiles = File::files($modelPath);
        foreach ($modelFiles as $file) {
            $modelName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $modelClass = "App\\Models\\" . $modelName;

            if (class_exists($modelClass) && is_subclass_of($modelClass, Model::class)) {
                $tableName = (new $modelClass())->getTable();
                if (Schema::hasTable($tableName) && $modelClass::count() === 0) {
                    $this->call('db:seed', ['--class' => $modelName . 'Seeder']);
                    $this->info("Seeding abgeschlossen für: $modelName");
                }
            }
        }
    }

    private function addFillableAttributes($modelPath)
    {
        $modelFiles = File::files($modelPath);
        foreach ($modelFiles as $file) {
            $filePath = $file->getPathname();
            $content = File::get($filePath);
            
            if (strpos($content, 'protected $fillable') === false) {
                preg_match_all('/\$table->(string|integer|boolean|text)\(\'([a-zA-Z0-9_]+)\'/', $content, $matches);
                $fillableAttributes = $matches[2] ?? [];
                if (!empty($fillableAttributes)) {
                    $fillableString = "protected \$fillable = ['" . implode("', '", $fillableAttributes) . "'];";
                    $content = preg_replace('/class ([a-zA-Z0-9_]+) extends Model/', "class $1 extends Model\n{\n    $fillableString", $content);
                    File::put($filePath, $content);
                    $this->info("Fillable Attribute hinzugefügt für: " . $file->getFilename());
                }
            }
        }
    }
}
