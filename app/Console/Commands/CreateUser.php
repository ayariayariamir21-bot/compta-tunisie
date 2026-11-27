<?php

namespace App\Console\Commands;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyMembershipService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates a user from the command line — the way to create the first
 * administrator when public registration is disabled in production.
 *
 * A strong random password is generated and shown once, unless the account
 * should receive a password reset e-mail instead (--send-reset-link).
 */
final class CreateUser extends Command
{
    protected $signature = 'app:create-user
        {email : Adresse e-mail de connexion}
        {name : Nom complet}
        {--company= : Identifiant de la société à laquelle donner accès}
        {--role=admin : Rôle dans la société (admin, accountant, viewer)}
        {--send-reset-link : Envoyer un e-mail de choix du mot de passe au lieu d\'afficher un mot de passe}';

    protected $description = 'Créer un utilisateur (et éventuellement lui donner accès à une société)';

    public function handle(CompanyMembershipService $memberships): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->argument('name'));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'role' => $this->option('role')],
            [
                'email' => ['required', 'email', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'role' => ['required', 'in:'.implode(',', array_map(fn (CompanyRole $role): string => $role->value, CompanyRole::cases()))],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $company = null;
        if ($this->option('company') !== null) {
            $company = Company::find((int) $this->option('company'));

            if ($company === null) {
                $this->error('Société introuvable : '.$this->option('company'));

                return self::FAILURE;
            }
        }

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->error("Un utilisateur existe déjà avec l'adresse {$email}.");

            return self::FAILURE;
        }

        if ($company !== null) {
            $result = $memberships->addMember($company, $name, $email, CompanyRole::from((string) $this->option('role')));
            $user = $result['user'];
            $this->info("Utilisateur créé et ajouté à « {$company->name} » (".CompanyRole::from((string) $this->option('role'))->label().').');
            $this->line('Un e-mail de choix du mot de passe a été envoyé à '.$user->email.'.');

            return self::SUCCESS;
        }

        $password = Str::password(16);
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ])->save();

        $this->info("Utilisateur créé : {$user->email}");

        if ($this->option('send-reset-link')) {
            Password::broker()->sendResetLink(['email' => $user->email]);
            $this->line('Un e-mail de choix du mot de passe a été envoyé.');
        } else {
            $this->line('Mot de passe temporaire (affiché une seule fois, à changer après connexion) :');
            $this->line($password);
        }

        $this->line("Après connexion, l'utilisateur peut créer sa société depuis « Entreprises ».");

        return self::SUCCESS;
    }
}
