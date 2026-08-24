<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\User;
use App\Services\Security\BackupService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin HTTP surface for backup operations; every business rule lives in
 * BackupService and every action is authorized before execution.
 */
final class BackupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->can('create', Backup::class), 403);

        $result = app(BackupService::class)->createDatabaseBackup($user, 'manual');

        if ($result['ok']) {
            return back()->with('status', 'Sauvegarde créée : '.$result['filename']);
        }

        return back()->with('error', $result['error']);
    }

    public function validate(Request $request, string $backup): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $service = app(BackupService::class);
        $metadata = $service->getBackup($backup);

        abort_if($metadata === null, 404);

        $resource = new Backup($backup);

        abort_unless($user->can('validate', $resource), 403);

        $validation = $service->validateBackup($backup);

        if ($validation['valid']) {
            $service->markValidated($backup, $user);

            return back()->with('status', 'Sauvegarde vérifiée : empreinte et archive valides.');
        }

        return back()->with('error', 'Sauvegarde invalide : '.implode(' ', $validation['errors']));
    }

    public function download(Request $request, string $backup): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $service = app(BackupService::class);
        $metadata = $service->getBackup($backup);

        abort_if($metadata === null, 404);

        $resource = new Backup($backup);

        abort_unless($user->can('download', $resource), 403);

        $service->auditDownloaded($backup, $metadata, $user);

        /** @var Filesystem $disk */
        $disk = Storage::disk(is_string(config('backups.disk')) ? (string) config('backups.disk') : 'local');

        return $disk->download(
            (string) config('backups.directory', 'backups').'/'.$backup,
            $backup,
        );
    }

    public function restore(Request $request, string $backup): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $service = app(BackupService::class);
        $metadata = $service->getBackup($backup);

        abort_if($metadata === null, 404);

        $resource = new Backup($backup);

        abort_unless($user->can('restore', $resource), 403);

        $confirmation = (string) $request->input('confirmation', '');

        $run = fn (): array => $service->restoreDatabaseBackup($backup, $user, $confirmation, [
            'via' => 'web_interface',
            'ip_address' => $request->ip(),
        ]);

        $result = config('backups.maintenance_during_restore')
            ? $service->underMaintenance($run)
            : $run();

        if ($result['ok']) {
            return back()->with('status', 'Base restaurée depuis '.$backup.'. Sauvegarde de sécurité : '.$result['safety_backup']);
        }

        return back()->with('error', $result['error']);
    }
}
