@use('App\Enums\NotificationSeverity')

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Notifications
            </h1>

            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                @if ($unreadCount > 0)
                    {{ $unreadCount }} notification(s) non lue(s).
                @else
                    Vous êtes à jour.
                @endif
            </p>
        </div>

        @if ($unreadCount > 0)
            <button
                type="button"
                wire:click="markAllAsRead"
                class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-800"
            >
                Tout marquer comme lu
            </button>
        @endif
    </div>

    <div class="flex flex-wrap items-end gap-3 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div>
            <flux:select label="État" wire:model.live="state" size="sm" class="w-40">
                <flux:select.option value="all">Toutes</flux:select.option>
                <flux:select.option value="unread">Non lues</flux:select.option>
                <flux:select.option value="read">Lues</flux:select.option>
            </flux:select>
        </div>

        <div>
            <flux:select label="Type" wire:model.live="type" size="sm" class="w-48">
                <flux:select.option value="">Tous les types</flux:select.option>
                @foreach ($types as $type)
                    <flux:select.option value="{{ $type['value'] }}">{{ $type['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div>
            <flux:select label="Société" wire:model.live="company" size="sm" class="w-52">
                <flux:select.option value="">Toutes les sociétés</flux:select.option>
                @foreach ($companies as $membershipCompany)
                    <flux:select.option value="{{ $membershipCompany['id'] }}">{{ $membershipCompany['name'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <ul class="divide-y divide-neutral-200 dark:divide-neutral-700">
            @forelse ($notifications as $notification)
                <li wire:key="notif-page-{{ $notification['id'] }}" class="px-5 py-4 {{ $notification['read'] ? '' : 'bg-blue-50/50 dark:bg-blue-950/20' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                @if (! $notification['read'])
                                    <span class="inline-block size-2 shrink-0 rounded-full bg-blue-600"></span>
                                @endif

                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $notification['title'] }}
                                </p>

                                @if ($notification['type'] !== null)
                                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                        {{ $notification['type']->label() }}
                                    </span>
                                @endif

                                @if (in_array($notification['severity'], [NotificationSeverity::Warning, NotificationSeverity::Danger], true))
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                        {{ $notification['severity']->label() }}
                                    </span>
                                @endif
                            </div>

                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $notification['message'] }}
                            </p>

                            <p class="mt-1.5 text-xs text-neutral-400">{{ $notification['created_at'] }}</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($notification['url'] !== null)
                                <a href="{{ $notification['url'] }}" wire:navigate class="rounded-md border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-700">
                                    Ouvrir
                                </a>
                            @endif

                            @if (! $notification['read'])
                                <button
                                    type="button"
                                    wire:click="markAsRead('{{ $notification['id'] }}')"
                                    class="rounded-md border border-neutral-300 px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-700"
                                >
                                    Marquer comme lu
                                </button>
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-sm text-neutral-500 dark:text-neutral-400">
                    Aucune notification ne correspond à ces critères.
                </li>
            @endforelse
        </ul>
    </div>

    <div class="mx-auto max-w-fit">
        {{ $notifications->links() }}
    </div>
</div>
