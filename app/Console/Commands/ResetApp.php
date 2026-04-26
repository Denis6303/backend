<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\EventSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // Sur certains hébergements, ces dossiers peuvent être absents après deploy.
        // Sans eux, SESSION_DRIVER=file plante avec "storage/framework/sessions ... No such file or directory".
        $this->ensureRuntimeDirectories();

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
        $migrateFreshExit = Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);
        $this->line(Artisan::output());
        if ($migrateFreshExit !== 0) {
            $this->error('Échec de migrate:fresh --seed. Vérifiez la sortie ci-dessus.');

            return self::FAILURE;
        }

        // Filet de sécurité: garantir des events de démonstration après reset.
        // (utile si un seeder échoue silencieusement en environnement distant)
        if (Event::query()->count() === 0) {
            $this->warn('Aucun événement détecté après --seed. Relance de EventSeeder...');
            $eventSeedExit = Artisan::call('db:seed', [
                '--class' => EventSeeder::class,
                '--force' => true,
            ]);
            $this->line(Artisan::output());
            if ($eventSeedExit !== 0 || Event::query()->count() === 0) {
                $this->error('EventSeeder a échoué ou n\'a créé aucun événement.');

                return self::FAILURE;
            }
        }

        // Sécurité prod: s'assurer que les tables Passport existent bien avant
        // d'appeler passport:client (sinon SQLSTATE 42S02 sur oauth_clients).
        if (! $this->ensurePassportTables()) {
            $this->error('Les tables OAuth Passport sont absentes. Vérifiez les migrations 2016_06_01_*.php et relancez la commande.');

            return self::FAILURE;
        }

        // Ensure storage symlink exists so seeded images are publicly accessible.
        $this->info('Vérification du lien de stockage public (storage:link)...');
        Artisan::call('storage:link');
        $this->line(Artisan::output());

        // On some local environments (especially on Windows), the storage:link
        // command may fail silently or a normal "storage" directory may exist.
        // In that case, try to recreate the link so that /storage URLs work.
        $publicStorage = public_path('storage');
        $target = storage_path('app/public');
        try {
            if (is_dir($publicStorage) && ! is_link($publicStorage)) {
                // Replace an existing plain directory by a proper symlink.
                File::deleteDirectory($publicStorage);
            }

            if (! file_exists($publicStorage)) {
                File::link($target, $publicStorage);
                $this->info('Lien symbolique public/storage recréé manuellement.');
            }
        } catch (\Throwable $e) {
            $this->warn('Impossible de créer le lien public/storage: '.$e->getMessage());
        }

        $this->info('(Re)création du client Passport personal access...');
        Artisan::call('passport:client', [
            '--personal' => true,
            '--name' => 'Votix Personal Access',
            '--no-interaction' => true,
        ]);
        $this->line(Artisan::output());

        // Client « client_credentials » : obligatoire pour le frontend Laravel (ApiService → oauth/token).
        $this->info('Création du client Passport « client credentials » (frontend public)...');
        Artisan::call('passport:client', [
            '--client' => true,
            '--name' => 'Votix Frontend',
            '--no-interaction' => true,
        ]);
        $this->line(Artisan::output());
        $this->newLine();
        $this->warn('Frontend : copiez le Client ID / Secret affichés ci-dessus dans le .env du projet frontend (VOTIX_API_CLIENT_ID et VOTIX_API_CLIENT_SECRET), puis php artisan config:clear sur le frontend.');

        // Admin dashboard: connexion automatique au backend via mode "password".
        // Objectif: éviter la régénération manuelle d'un PAT à chaque app:reset.
        $this->info('Configuration de la connexion API pour l\'admin (mode password)...');
        $adminServiceEmail = env('ADMIN_SERVICE_EMAIL', 'admin.service@votxevent.local');
        $adminServicePassword = env('ADMIN_SERVICE_PASSWORD');
        if (! is_string($adminServicePassword) || trim($adminServicePassword) === '') {
            $adminServicePassword = 'AdminSvc@'.Str::random(10);
        }

        /** @var User $adminServiceUser */
        $adminServiceUser = User::query()->updateOrCreate(
            ['email' => $adminServiceEmail],
            [
                'first_name' => 'Admin',
                'last_name' => 'Service',
                'password' => Hash::make($adminServicePassword),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
        $adminServiceUser->forceFill(['is_admin' => true])->save();

        $adminProjectPath = base_path('../admin');
        $adminEnvPath = $adminProjectPath.DIRECTORY_SEPARATOR.'.env';
        if (File::exists($adminEnvPath)) {
            $this->setEnvValue($adminEnvPath, 'BACKEND_ADMIN_API_URL', 'http://127.0.0.1:8000/api/admin');
            $this->setEnvValue($adminEnvPath, 'BACKEND_ADMIN_AUTH', 'password');
            $this->setEnvValue($adminEnvPath, 'BACKEND_ADMIN_LOGIN_URL', 'http://127.0.0.1:8000/api/v1/auth/login');
            $this->setEnvValue($adminEnvPath, 'BACKEND_ADMIN_SERVICE_EMAIL', $adminServiceEmail);
            $this->setEnvValue($adminEnvPath, 'BACKEND_ADMIN_SERVICE_PASSWORD', $adminServicePassword);
            // Ne pas garder un ancien PAT quand le mode password est activé.
            $this->setEnvValue($adminEnvPath, 'BACKEND_ADMIN_API_TOKEN', '');
            $this->info('Admin .env mis à jour automatiquement (mode password).');
            $this->warn('Admin : exécutez `php artisan config:clear` dans le projet admin si le serveur était déjà lancé.');
        } else {
            $this->warn('Fichier admin/.env introuvable. Configurez manuellement BACKEND_ADMIN_AUTH=password, BACKEND_ADMIN_LOGIN_URL, BACKEND_ADMIN_SERVICE_EMAIL et BACKEND_ADMIN_SERVICE_PASSWORD.');
            $this->line('BACKEND_ADMIN_SERVICE_EMAIL='.$adminServiceEmail);
            $this->line('BACKEND_ADMIN_SERVICE_PASSWORD='.$adminServicePassword);
        }

        $this->info('Réinitialisation terminée.');

        return self::SUCCESS;
    }

    private function ensurePassportTables(): bool
    {
        $required = [
            'oauth_clients',
            'oauth_access_tokens',
            'oauth_refresh_tokens',
            'oauth_auth_codes',
            'oauth_personal_access_clients',
        ];

        $allPresent = static fn (): bool => collect($required)->every(
            fn (string $table): bool => Schema::hasTable($table)
        );

        if ($allPresent()) {
            return true;
        }

        $this->warn('Tables Passport manquantes après migrate:fresh. Tentative de migration ciblée OAuth...');
        Artisan::call('migrate', ['--force' => true]);
        $this->line(Artisan::output());

        return $allPresent();
    }

    private function setEnvValue(string $envPath, string $key, string $value): void
    {
        $content = File::get($envPath);
        $normalizedValue = str_replace(["\r", "\n"], '', $value);
        $line = $key.'='.$normalizedValue;

        if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $content) === 1) {
            $content = preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $content) ?? $content;
        } else {
            $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
        }

        File::put($envPath, $content);
    }

    private function ensureRuntimeDirectories(): void
    {
        $dirs = [
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/testing'),
            storage_path('logs'),
        ];

        foreach ($dirs as $dir) {
            try {
                if (! File::exists($dir)) {
                    File::makeDirectory($dir, 0755, true, true);
                    $this->line("Dossier runtime créé: {$dir}");
                }
            } catch (\Throwable $e) {
                $this->warn("Impossible de créer {$dir}: ".$e->getMessage());
            }
        }
    }
}

