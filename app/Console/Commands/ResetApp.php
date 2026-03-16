<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ResetApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Exemple: php artisan app:reset --force
     */
    protected $signature = 'app:reset {--force : Forcer l\'exécution en production}';

    /**
     * The console command description.
     */
    protected $description = 'Nettoie la base de données, refait les migrations, nettoie les caches et relance les seeders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (App::environment('production') && ! $this->option('force')) {
            $this->error('Refus d\'exécuter app:reset en production sans --force.');

            return self::FAILURE;
        }

        $this->info('Nettoyage des caches (config, route, vue, cache, opcache)...');
        Artisan::call('optimize:clear');
        $this->line(Artisan::output());

        // Nettoyer les sessions (déconnecte tous les utilisateurs web)
        $this->info('Nettoyage des sessions (déconnexion des utilisateurs)...');
        File::cleanDirectory(storage_path('framework/sessions'));

        // Nettoyer les fichiers uploadés (storage/app/public/*)
        // Cela évite l'accumulation d'images après plusieurs resets.
        $this->info('Nettoyage des fichiers uploadés (storage/app/public)...');
        try {
            $disk = Storage::disk('public');

            // Delete everything under the public disk root, except .gitignore
            foreach ($disk->allFiles() as $file) {
                if (basename($file) === '.gitignore') {
                    continue;
                }
                $disk->delete($file);
            }

            foreach ($disk->allDirectories() as $dir) {
                $disk->deleteDirectory($dir);
            }
        } catch (\Throwable $e) {
            $this->warn('Impossible de nettoyer storage/app/public: ' . $e->getMessage());
        }

        $this->info('Réinitialisation de la base de données (migrate:fresh --seed)...');
        Artisan::call('migrate:fresh', ['--seed' => true]);
        $this->line(Artisan::output());

        $this->info('(Re)création du client Passport personal access...');
        Artisan::call('passport:client', [
            '--personal' => true,
            '--name' => 'Votix Personal Access',
            '--no-interaction' => true,
        ]);
        $this->line(Artisan::output());

        $this->info('Réinitialisation terminée.');

        return self::SUCCESS;
    }
}

