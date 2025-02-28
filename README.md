# Laravel Voyager zu Filament Migrationstool

Dieses Paket ermöglicht die Migration von **Voyager**-Modellen, -Controllern, -Traits und -Migrationen nach **Filament**. 

## 📌 Installation

Füge das Paket mit Composer hinzu:

```bash
composer require benwallmeyer/laravel-voyager-to-filament
```

## 🚀 Befehle

### **1. Export aus Voyager-Instanz**

Führe den folgenden Befehl in deiner **Voyager-Instanz** aus, um die Daten zu exportieren:

```bash
php artisan voyager-to-filament:export
```

📌 **Was passiert?**
- **Modelle** werden nach `app/Models` verschoben und ggf. der Namespace angepasst
- **Controller** werden nach `app/Http/Controllers` exportiert
- **Traits** werden aus allen möglichen Verzeichnissen (`app/Traits`, `app/Http/Traits`, etc.) übernommen
- **Migrationen** werden generiert und in `database/migrations` gespeichert
- **Alles wird in eine ZIP-Datei gepackt** (`storage/voyager_to_filament.zip`)

Falls du die ZIP an einem anderen Ort benötigst, kannst du sie einfach verschieben oder den Pfad beim Import angeben.

---

### **2. Import in Filament-Instanz**

Führe den folgenden Befehl in deiner **Filament-Instanz** aus:

```bash
php artisan voyager-to-filament:import
```

Falls sich die ZIP-Datei an einem anderen Speicherort befindet, kannst du sie explizit angeben:

```bash
php artisan voyager-to-filament:import /pfad/zu/deiner/datei.zip
```

📌 **Was passiert?**
- Die ZIP-Datei wird **entpackt** und alle Dateien werden in die richtigen Verzeichnisse kopiert
- **Modelle, Controller & Traits** werden importiert
- **Migrationen** werden ausgeführt (`php artisan migrate`)
- **Filament-Resources** für importierte Modelle werden automatisch erstellt

---

## 🔄 Beispiel Workflow

1️⃣ **In der Voyager-Instanz:**
```bash
php artisan voyager-to-filament:export
```

2️⃣ **ZIP-Datei in die Filament-Instanz kopieren**
```bash
mv storage/voyager_to_filament.zip /pfad/zur/filament/instanz/storage/
```

3️⃣ **In der Filament-Instanz importieren:**
```bash
php artisan voyager-to-filament:import
```

✅ **Erfolgreich von Voyager zu Filament migriert!** 🚀
