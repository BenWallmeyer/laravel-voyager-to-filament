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
        $voyagerModelsPath = app_path();
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

            // Namespace anpassen
            $content = File::get($filePath);
            $updatedContent = str_replace('namespace App;', 'namespace App\\Models;', $content);
            File::put($newFilePath, $updatedContent);

            $this->info("Model exportiert: $fileName");

            // Controller generieren
            $modelClassName = pathinfo($fileName, PATHINFO_FILENAME);
            $controllerFilePath = $controllerPath . "/{$modelClassName}Controller.php";
            $controllerStub = "<?php\n\nnamespace App\\Http\\Controllers;\n\nuse App\\Models\\" . $modelClassName . ";\nuse Illuminate\\Http\\Request;\n\nclass " . $modelClassName . "Controller extends Controller\n{\n    public function index()\n    {\n        return response()->json(" . $modelClassName . "::all());\n    }\n\n    public function show(\$id)\n    {\n        return response()->json(" . $modelClassName . "::findOrFail(\$id));\n    }\n\n    public function store(Request \$request)\n    {\n        return response()->json(" . $modelClassName . "::create(\$request->all()));\n    }\n\n    public function update(Request \$request, \$id)\n    {\n        \$item = " . $modelClassName . "::findOrFail(\$id);\n        \$item->update(\$request->all());\n        return response()->json(\$item);\n    }\n\n    public function destroy(\$id)\n    {\n        " . $modelClassName . "::findOrFail(\$id)->delete();\n        return response()->json(['message' => 'Deleted successfully']);\n    }\n}";
            
            File::put($controllerFilePath, $controllerStub);
            $this->info("Controller erstellt: {$modelClassName}Controller.php");
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
