<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
            Journal d’audit
        </h1>

        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            Historique complet et infalsifiable des actions effectuées dans votre société.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <flux:input wire:model.live.debounce.400ms="search" placeholder="Rechercher…" class="lg:col-span-2" />

        <select
            wire:model.live="action"
            class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
        >
            <option value="">Toutes les actions</option>
            @foreach ($actions as $option)
                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
            @endforeach
        </select>

        <select
            wire:model.live="userId"
            class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
        >
            <option value="">Tous les utilisateurs</option>
            @foreach ($users as $user)
                <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
            @endforeach
        </select>

        <input
            type="date"
            wire:model.live="dateFrom"
            class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
        />

        <input
            type="date"
            wire:model.live="dateTo"
            class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
        />
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
            <thead class="bg-neutral-50 dark:bg-neutral-800">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Action</th>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Utilisateur</th>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Entité</th>
                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Description</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($logs as $log)
                    <tr
                        wire:key="audit-log-{{ $log->id }}"
                        wire:click="showDetail({{ $log->id }})"
                        class="cursor-pointer transition hover:bg-neutral-50 dark:hover:bg-neutral-800"
                    >
                        <td class="px-5 py-4 whitespace-nowrap text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $log->created_at?->format('d/m/Y H:i:s') }}
                        </td>

                        <td class="px-5 py-4 whitespace-nowrap">
                            {{ \App\Enums\AuditAction::tryFrom($log->action)?->label() ?? $log->action }}
                        </td>

                        <td class="px-5 py-4 whitespace-nowrap text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $log->user?->name ?? __('Système') }}
                        </td>

                        <td class="px-5 py-4 whitespace-nowrap text-sm text-neutral-600 dark:text-neutral-300">
                            @if ($log->entity_type)
                                {{ $log->entity_type }} #{{ $log->entity_id }}
                            @else
                                —
                            @endif
                        </td>

                        <td class="max-w-md truncate px-5 py-4 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $log->description }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                            Aucune entrée d’audit ne correspond à ces critères.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}

    @if ($selected)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click="closeDetail">
            <div
                class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-neutral-200 bg-white p-6 shadow-xl dark:border-neutral-700 dark:bg-neutral-900"
                wire:click.stop
            >
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $selected['action_label'] }}
                        </h2>

                        <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
                            {{ $selected['created_at'] }}
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeDetail"
                        class="text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                    >
                        Fermer
                    </button>
                </div>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Utilisateur</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $selected['user_name'] }}@if ($selected['user_email']) <span class="text-neutral-500">({{ $selected['user_email'] }})</span>@endif</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Adresse IP</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $selected['ip_address'] ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Entité</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $selected['entity'] ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Route</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ trim(($selected['method'] ?? '').' '.($selected['route'] ?? '')) ?: '—' }}</dd>
                    </div>
                </dl>

                @if ($selected['description'])
                    <p class="mt-4 text-sm text-neutral-700 dark:text-neutral-300">{{ $selected['description'] }}</p>
                @endif

                @if ($selected['before_json'])
                    <div class="mt-4">
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Avant</h3>
                        <pre class="overflow-x-auto rounded-lg bg-neutral-100 p-3 text-xs text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200">{{ $selected['before_json'] }}</pre>
                    </div>
                @endif

                @if ($selected['after_json'])
                    <div class="mt-4">
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Après</h3>
                        <pre class="overflow-x-auto rounded-lg bg-neutral-100 p-3 text-xs text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200">{{ $selected['after_json'] }}</pre>
                    </div>
                @endif

                @if ($selected['metadata_json'])
                    <div class="mt-4">
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Détails</h3>
                        <pre class="overflow-x-auto rounded-lg bg-neutral-100 p-3 text-xs text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200">{{ $selected['metadata_json'] }}</pre>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
