<?php

namespace App\Livewire\Backups;

use App\Models\Backup;
use App\Models\User;
use App\Services\Security\BackupService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sauvegardes')]
final class Index extends Component
{
    public ?string $restoreTarget = null;

    public function mount(): void
    {
        if (! $this->authorizedUser()) {
            abort(403);
        }
    }

    /**
     * Prepare the restore confirmation modal for one backup.
     */
    public function openRestore(string $filename): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $metadata = app(BackupService::class)->getBackup($filename);

        if ($metadata === null || ! $user->can('restore', new Backup($filename))) {
            abort(403);
        }

        $this->restoreTarget = $filename;
    }

    public function closeRestore(): void
    {
        $this->restoreTarget = null;
    }

    public function render(): View
    {
        if (! $this->authorizedUser()) {
            abort(403);
        }

        /** @var User $user */
        $user = Auth::user();

        $service = app(BackupService::class);

        $rows = array_map(
            fn (array $row): array => $this->enrichRow($user, $row),
            $service->listBackups(),
        );

        return view('livewire.backups.index', [
            'backups' => $rows,
            'canCreate' => $user->can('create', Backup::class),
            'environment' => app()->environment(),
            'restoreAllowedEnvironment' => $this->restoreAllowedInEnvironment(),
            'confirmationPhrase' => (string) config('backups.confirmation_phrase'),
            'selectedRestore' => $this->restoreTarget !== null ? $this->resolveRestoreTarget($service) : null,
            'toolsAvailable' => $service->toolsAvailable(),
        ]);
    }

    private function authorizedUser(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('viewAny', Backup::class);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enrichRow(User $user, array $row): array
    {
        $resource = new Backup((string) ($row['filename'] ?? ''));

        $row['created_at_display'] = null;

        if (is_string($row['created_at'] ?? null)) {
            try {
                $row['created_at_display'] = CarbonImmutable::parse((string) $row['created_at'])->format('d/m/Y H:i:s');
            } catch (\Throwable) {
                $row['created_at_display'] = null;
            }
        }

        $row['size_human'] = $this->humanSize((int) ($row['size'] ?? 0));

        $details = array_filter([
            is_string($row['database'] ?? null) ? $row['database'] : null,
            is_string($row['postgresql_version'] ?? null) ? $row['postgresql_version'] : null,
            is_string($row['application_commit'] ?? null) ? 'commit '.$row['application_commit'] : null,
        ]);

        $row['details'] = implode(' · ', $details);

        $row['permissions'] = [
            'validate' => $user->can('validate', $resource),
            'download' => $user->can('download', $resource),
            'restore' => $user->can('restore', $resource),
        ];

        return $row;
    }

    /**
     * Resolve the restore-modal target with display-ready fields.
     *
     * @return array<string, mixed>|null
     */
    private function resolveRestoreTarget(BackupService $service): ?array
    {
        $metadata = $service->getBackup((string) $this->restoreTarget);

        if ($metadata === null) {
            return null;
        }

        if (is_string($metadata['created_at'] ?? null)) {
            try {
                $metadata['created_at_display'] = CarbonImmutable::parse((string) $metadata['created_at'])->format('d/m/Y H:i:s');
            } catch (\Throwable) {
                $metadata['created_at_display'] = null;
            }
        } else {
            $metadata['created_at_display'] = null;
        }

        $metadata['size_human'] = $this->humanSize((int) ($metadata['size'] ?? 0));

        return $metadata;
    }

    private function restoreAllowedInEnvironment(): bool
    {
        $allowed = config('backups.allowed_environments');

        return is_array($allowed) && in_array(app()->environment(), $allowed, true);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return sprintf('%.1f Mo', $bytes / 1048576);
        }

        if ($bytes >= 1024) {
            return sprintf('%.1f Ko', $bytes / 1024);
        }

        return $bytes.' o';
    }
}
