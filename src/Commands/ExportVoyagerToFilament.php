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
    $updatedContent = str_replace('namespace App;', 'namespace App\Models;', $content);
    File::put($newFilePath, $updatedContent);

    $this->info("Model exportiert: $fileName");

    // Model-Klassenname extrahieren
    $modelClassName = pathinfo($fileName, PATHINFO_FILENAME);

    // Controller generieren
    $controllerPath = $exportPath . "/app/Http/Controllers/{$modelClassName}Controller.php";
    $controllerStub = "<?php

namespace App\Http\Controllers;

use App\Models\\$modelClassName;
use Illuminate\Http\Request;

class {$modelClassName}Controller extends Controller
{
    public function index()
    {
        return response()->json({$modelClassName}::all());
    }

    public function show(\$id)
    {
        return response()->json({$modelClassName}::findOrFail(\$id));
    }

    public function store(Request \$request)
    {
        return response()->json({$modelClassName}::create(\$request->all()));
    }

    public function update(Request \$request, \$id)
    {
        \$item = {$modelClassName}::findOrFail(\$id);
        \$item->update(\$request->all());
        return response()->json(\$item);
    }

    public function destroy(\$id)
    {
        {$modelClassName}::findOrFail(\$id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}";

    File::put($controllerPath, $controllerStub);
    $this->info("Controller erstellt: {$modelClassName}Controller.php");
}
