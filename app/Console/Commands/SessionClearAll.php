<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Vide le stockage des sessions Laravel lorsque le driver est "file".
 * Équivalent utile à lancer après un reset local ou pour forcer une déconnexion globale côté serveur.
 */
class SessionClearAll extends Command
{
    protected $signature = 'session:clear-all
                            {--dry-run : Afficher le chemin cible sans supprimer de fichiers}';

    protected $description = 'Supprime les fichiers de session (driver file) — déconnecte les utilisateurs web sur cette instance';

    public function handle(): int
    {
        $dir = storage_path('framework/sessions');

        if (! is_dir($dir)) {
            $this->warn("Répertoire introuvable : {$dir}");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line("Dry-run — cible : {$dir}");

            return self::SUCCESS;
        }

        File::cleanDirectory($dir);
        $this->info("Sessions fichier vidées : {$dir}");

        return self::SUCCESS;
    }
}
