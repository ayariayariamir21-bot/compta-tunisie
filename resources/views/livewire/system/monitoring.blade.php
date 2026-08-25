@use('App\Enums\HealthStatus')

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Monitoring
            </h1>

            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                État opérationnel interne — réservé aux administrateurs.
            </p>
        </div>

        <button
            type="button"
            wire:click="refresh"
            class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-800"
        >
            Actualiser
        </button>
    </div>

    <div class="rounded-xl border p-5 shadow-sm {{ $overall === HealthStatus::Ok ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950' : ($overall === HealthStatus::Warning ? 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950') }}">
        <p class="text-sm font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">État général</p>

        <p class="mt-1 text-2xl font-bold {{ $overall === HealthStatus::Ok ? 'text-green-700 dark:text-green-300' : ($overall === HealthStatus::Warning ? 'text-amber-700 dark:text-amber-300' : 'text-red-700 dark:text-red-300') }}">
            {{ $overall->label() }}
        </p>
    </div>

    @if (count($alerts) > 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Conditions d'alerte détectées</p>

            <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-amber-900 dark:text-amber-200">
                @foreach ($alerts as $alert)
                    <li wire:key="alert-{{ md5($alert['key'].$alert['message']) }}">
                        [{{ $alert['status']->label() }}] {{ $alert['message'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            'application' => 'Application',
            'database' => 'Base de données',
            'cache' => 'Cache',
            'queue' => 'File & jobs',
            'storage' => 'Stockage',
            'backups' => 'Sauvegardes',
            'scheduler' => 'Planificateur',
        ] as $key => $label)
            @php
                $card = $cards[$key];
                $border = $card['status'] === HealthStatus::Ok ? 'border-green-300 dark:border-green-800' : ($card['status'] === HealthStatus::Warning ? 'border-amber-300 dark:border-amber-700' : 'border-red-300 dark:border-red-800');
                $dot = $card['status'] === HealthStatus::Ok ? 'bg-green-500' : ($card['status'] === HealthStatus::Warning ? 'bg-amber-500' : 'bg-red-500');
            @endphp

            <div wire:key="card-{{ $key }}" class="rounded-xl border bg-white p-5 shadow-sm {{ $border }} dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-neutral-900 dark:text-white">{{ $label }}</span>

                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-600 dark:text-neutral-400">
                        <span class="inline-block size-2 rounded-full {{ $dot }}"></span>
                        {{ $card['status']->label() }}
                    </span>
                </div>

                <p class="mt-3 text-sm text-neutral-700 dark:text-neutral-300">{{ $card['message'] }}</p>

                @if ($key === 'queue')
                    <dl class="mt-3 space-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                        <div class="flex justify-between"><dt>Driver</dt><dd class="font-mono">{{ $card['driver'] }}</dd></div>
                        <div class="flex justify-between"><dt>Jobs échoués</dt><dd>{{ $card['failed_jobs'] }}</dd></div>
                    </dl>
                @elseif ($key === 'backups')
                    <dl class="mt-3 space-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                        <div class="flex justify-between"><dt>Sauvegardes</dt><dd>{{ $card['count'] }}</dd></div>
                        @if (is_array($card['latest']))
                            <div class="flex justify-between"><dt>Dernière</dt><dd>{{ $card['latest']['created_at'] ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt>Âge</dt><dd>{{ $card['latest']['age_hours'] ?? '—' }} h</dd></div>
                            <div class="flex justify-between"><dt>Empreinte</dt><dd>{{ $card['latest']['checksum_status'] === 'ok' ? 'OK' : 'Inconnue' }}</dd></div>
                        @endif
                    </dl>
                @endif

                <p class="mt-3 text-[11px] text-neutral-400">{{ $card['duration_ms'] }} ms</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <p class="text-sm font-semibold text-neutral-900 dark:text-white">Exécution</p>

            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs text-neutral-600 dark:text-neutral-400">
                <dt>PHP</dt><dd class="font-mono">{{ $runtime['php'] }}</dd>
                <dt>Laravel</dt><dd class="font-mono">{{ $runtime['laravel'] }}</dd>
                <dt>Environnement</dt><dd>{{ $runtime['environment'] }}</dd>
                <dt>Débogage</dt><dd>{{ $runtime['debug'] ? 'Activé (interdit en production)' : 'Désactivé' }}</dd>
                <dt>URL HTTPS</dt><dd>{{ $runtime['https_url'] ? 'Oui' : 'Non' }}</dd>
                <dt>Outils sauvegarde</dt><dd>{{ $runtime['backup_tools'] ? 'Disponibles' : 'Indisponibles' }}</dd>
                <dt>Mémoire courante</dt><dd>{{ $runtime['memory_current_mb'] }} Mo</dd>
                <dt>Mémoire crête</dt><dd>{{ $runtime['memory_peak_mb'] }} Mo</dd>
            </dl>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <p class="text-sm font-semibold text-neutral-900 dark:text-white">Jobs échoués</p>

            <dl class="mt-3 space-y-2 text-xs text-neutral-600 dark:text-neutral-400">
                <div class="flex justify-between"><dt>Total</dt><dd>{{ $runtime['failed_jobs_last']['count'] }}</dd></div>
                <div class="flex justify-between"><dt>Dernier échec</dt><dd>{{ $runtime['failed_jobs_last']['last_failed_at'] ?? '—' }}</dd></div>
                <div class="mt-2 break-words"><dt class="mb-1">Signature</dt><dd class="font-mono">{{ $runtime['failed_jobs_last']['last_job'] ?? 'Aucune' }}</dd></div>
            </dl>
        </div>
    </div>
</div>
