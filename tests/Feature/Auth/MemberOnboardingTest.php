<?php

use App\Enums\AuditAction;
use App\Enums\CompanyRole;
use App\Livewire\Companies\Members;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->company = Company::create(['name' => 'Cabinet SARL', 'currency' => 'TND', 'is_active' => true]);
    $this->company->users()->attach($this->admin, ['role' => 'admin', 'is_active' => true]);
    $this->actingAs($this->admin);
    session(['current_company_id' => $this->company->id]);
});

it('creates an account for a new member and sends a password link', function () {
    Notification::fake();

    Livewire::test(Members::class)
        ->set('newMemberName', 'Sami Comptable')
        ->set('newMemberEmail', 'Sami@Example.com')
        ->set('newMemberRole', 'accountant')
        ->call('addMember')
        ->assertHasNoErrors();

    $member = User::where('email', 'sami@example.com')->firstOrFail();

    expect($member->email_verified_at)->not->toBeNull()
        ->and($this->company->users()->whereKey($member->id)->first()->pivot->role)->toBe('accountant')
        ->and(AuditLog::where('action', AuditAction::MemberAdded->value)->exists())->toBeTrue();
    Notification::assertSentTo($member, ResetPassword::class);
});

it('gives an existing account access without creating a new one', function () {
    $existing = User::factory()->create(['email' => 'existant@example.com']);

    Livewire::test(Members::class)
        ->set('newMemberEmail', 'existant@example.com')
        ->set('newMemberRole', 'viewer')
        ->call('addMember')
        ->assertHasNoErrors();

    expect(User::where('email', 'existant@example.com')->count())->toBe(1)
        ->and($this->company->users()->whereKey($existing->id)->first()->pivot->role)->toBe('viewer');
});

it('refuses to add someone who is already a member', function () {
    Livewire::test(Members::class)
        ->set('newMemberEmail', $this->admin->email)
        ->set('newMemberRole', 'viewer')
        ->call('addMember')
        ->assertSee('déjà membre');
});

it('requires a name to create a brand new account', function () {
    Livewire::test(Members::class)
        ->set('newMemberName', '')
        ->set('newMemberEmail', 'inconnu@example.com')
        ->call('addMember')
        ->assertSee('nom est obligatoire');

    expect(User::where('email', 'inconnu@example.com')->exists())->toBeFalse();
});

it('forbids non administrators from adding members', function () {
    $accountant = User::factory()->create();
    $this->company->users()->attach($accountant, ['role' => 'accountant', 'is_active' => true]);
    $this->actingAs($accountant);

    Livewire::test(Members::class)->assertForbidden();
});

it('creates the first administrator from the command line', function () {
    $this->artisan('app:create-user', ['email' => 'Patron@Client.tn', 'name' => 'Le Patron'])
        ->expectsOutputToContain('Utilisateur créé : patron@client.tn')
        ->assertSuccessful();

    $user = User::where('email', 'patron@client.tn')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull()
        ->and(Hash::needsRehash($user->password))->toBeFalse();
});

it('adds a command-line user to a company with a role', function () {
    Notification::fake();

    $this->artisan('app:create-user', ['email' => 'compta@client.tn', 'name' => 'Comptable', '--company' => $this->company->id, '--role' => 'accountant'])
        ->assertSuccessful();

    $user = User::where('email', 'compta@client.tn')->firstOrFail();

    expect($this->company->users()->whereKey($user->id)->first()->pivot->role)->toBe(CompanyRole::Accountant->value);
    Notification::assertSentTo($user, ResetPassword::class);
});

it('rejects a duplicate e-mail on the command line', function () {
    $this->artisan('app:create-user', ['email' => $this->admin->email, 'name' => 'Doublon'])
        ->assertFailed();
});

it('can disable public registration from the environment', function () {
    $_ENV['REGISTRATION_ENABLED'] = $_SERVER['REGISTRATION_ENABLED'] = 'false';

    try {
        $features = (require config_path('fortify.php'))['features'];
    } finally {
        unset($_ENV['REGISTRATION_ENABLED'], $_SERVER['REGISTRATION_ENABLED']);
    }

    expect($features)->not->toContain(Features::registration())
        ->and($features)->toContain(Features::resetPasswords());
});

it('shows the members link in the menu to administrators only', function () {
    $this->get(route('dashboard'))->assertSee(route('companies.members'));

    $viewer = User::factory()->create();
    $this->company->users()->attach($viewer, ['role' => 'viewer', 'is_active' => true]);

    $this->actingAs($viewer)->get(route('dashboard'))->assertDontSee(route('companies.members'));
});
