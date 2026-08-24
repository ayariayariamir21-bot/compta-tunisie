<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Gestion des sauvegardes
            </h1>

            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Sauvegardes complètes de la base de données PostgreSQL, stockées hors d'accès public.
            </p>
        </div>

        @if ($canCreate)
            <form method="POST" action="{{ route('backups.create') }}">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    {{ $toolsAvailable ? '' : 'disabled title="Outils PostgreSQL indisponibles"' }}
                >
                    Créer une sauvegarde
                </button>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    @if (! $restoreAllowedEnvironment)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
            Restauration de production désactivée depuis l'interface.
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
            <thead class="bg-neutral-50 dark:bg-neutral-800">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Nom</th>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Taille</th>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Checksum</th>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Statut</th>
                    <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($backups as $backup)
                    <tr wire:key="backup-{{ $backup['filename'] }}" class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $backup['created_at_display'] ?? '—' }}
                        </td>

                        <td class="px-5 py-4 font-mono text-xs text-neutral-700 dark:text-neutral-300">
                            {{ $backup['filename'] }}

                            @if (($backup['details'] ?? '') !== '')
                                <div class="mt-0.5 font-sans text-[11px] font-normal text-neutral-400 dark:text-neutral-500">
                                    {{ $backup['details'] }}
                                </div>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-5 py-4 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $backup['size_human'] }}
                        </td>

                        <td class="whitespace-nowrap px-5 py-4 text-sm">
                            @if ($backup['checksum_status'] === 'ok')
                                <span class="text-green-700 dark:text-green-400">Vérifié (métadonnées)</span>
                            @else
                                <span class="text-amber-700 dark:text-amber-400">À vérifier</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-5 py-4 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $backup['status'] === 'validated' ? 'Validée' : ($backup['status'] === 'inconnu' ? 'Inconnu' : 'Créée') }}
                        </td>

                        <td class="whitespace-nowrap px-5 py-4 text-right text-sm">
                            <div class="flex justify-end gap-2">
                                @if ($backup['permissions']['validate'])
                                    <form method="POST" action="{{ route('backups.validate', $backup['filename']) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-700">
                                            Vérifier
                                        </button>
                                    </form>
                                @endif

                                @if ($backup['permissions']['download'])
                                    <a href="{{ route('backups.download', $backup['filename']) }}" wire:navigate.prefetch class="rounded-md border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-700">
                                        Télécharger
                                    </a>
                                @endif

                                @if ($backup['permissions']['restore'])
                                    <button type="button" wire:click="openRestore('{{ $backup['filename'] }}')" class="rounded-md border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950">
                                        Restaurer
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                            Aucune sauvegarde disponible pour le moment.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($selectedRestore !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click="closeRestore">
            <div
                class="max-h-[85vh] w-full max-w-xl overflow-y-auto rounded-xl border border-neutral-200 bg-white p-6 shadow-xl dark:border-neutral-700 dark:bg-neutral-900"
                wire:click.stop
            >
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Restaurer cette sauvegarde ?
                </h2>

                <p class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                    Attention : la restauration remplace l'intégralité du contenu actuel de la base de données.
                    Une sauvegarde de sécurité sera créée juste avant l'opération.
                </p>

                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Fichier</dt>
                        <dd class="font-mono text-xs text-gray-900 dark:text-white">{{ $selectedRestore['filename'] }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Date</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $selectedRestore['created_at_display'] ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Taille</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $selectedRestore['size_human'] }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Statut</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">
                            @if (($selectedRestore['status'] ?? '') === 'validated')
                                Validée
                            @elseif (($selectedRestore['status'] ?? '') === 'created')
                                Créée (non vérifiée)
                            @else
                                Inconnu
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Environnement</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $environment }}</dd>
                    </div>
                </dl>

                <p class="mt-3 break-all text-xs text-neutral-500 dark:text-neutral-400">
                    Empreinte SHA-256 : <span class="font-mono">{{ $selectedRestore['checksum'] ?? '—' }}</span>
                </p>

                @if (! $restoreAllowedEnvironment)
                    <p class="mt-4 text-sm font-medium text-red-700 dark:text-red-400">
                        Restauration de production désactivée depuis l'interface.
                    </p>
                @else
                    <form method="POST" action="{{ route('backups.restore', $selectedRestore['filename']) }}" class="mt-4 space-y-3">
                        @csrf

                        <label class="block text-sm text-neutral-700 dark:text-neutral-300">
                            Pour confirmer, saisissez exactement :
                            <span class="font-mono font-semibold">{{ $confirmationPhrase }}</span>
                            <input
                                type="text"
                                name="confirmation"
                                required
                                autocomplete="off"
                                pattern="{{ preg_quote($confirmationPhrase, '/') }}"
                                class="mt-1 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                            />
                        </label>

                        <div class="flex items-center justify-between gap-3 pt-2">
                            <a href="{{ route('backups.index') }}" wire:navigate class="rounded-md border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-700">Annuler</a>

                            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700">
                                Restaurer la base
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
