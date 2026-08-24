<flux:dropdown position="bottom" align="end">
    <span class="relative inline-flex">
        <flux:button variant="ghost" icon="bell" size="sm" aria-label="Notifications" />
        @if ($unreadCount > 0)
            <span class="pointer-events-none absolute -right-0.5 -top-0.5 z-10 inline-flex min-w-[18px] items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-semibold leading-[18px] text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </span>

    <div class="w-80 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xl dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
            <span class="text-sm font-semibold text-neutral-900 dark:text-white">Notifications</span>

            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">
                    Tout marquer comme lu
                </button>
            @endif
        </div>

        <div class="max-h-96 divide-y divide-neutral-200 overflow-y-auto dark:divide-neutral-700">
            @forelse ($items as $item)
                <div wire:key="notif-{{ $item['id'] }}" class="px-4 py-3 {{ $item['read'] ? '' : 'bg-blue-50/60 dark:bg-blue-950/30' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-neutral-900 dark:text-white">
                                @if ($item['severity'] === 'warning' || $item['severity'] === 'danger')
                                    <span class="mr-1 inline-block size-2 rounded-full bg-amber-500"></span>
                                @elseif ($item['severity'] === 'success')
                                    <span class="mr-1 inline-block size-2 rounded-full bg-green-500"></span>
                                @else
                                    <span class="mr-1 inline-block size-2 rounded-full bg-blue-500"></span>
                                @endif
                                {{ $item['title'] }}
                            </p>

                            <p class="mt-0.5 truncate text-xs text-neutral-600 dark:text-neutral-400">{{ $item['message'] }}</p>

                            <p class="mt-1 text-[11px] text-neutral-400">
                                {{ $item['date'] }}@if ($item['type_label'] !== '') · {{ $item['type_label'] }}@endif
                            </p>
                        </div>

                        @if (! $item['read'])
                            <button
                                type="button"
                                wire:click="markAsRead('{{ $item['id'] }}')"
                                class="shrink-0 text-[11px] font-medium text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200"
                                title="Marquer comme lu"
                            >
                                Lu
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">Aucune notification.</p>
            @endforelse
        </div>

        <a href="{{ route('notifications.index') }}" wire:navigate class="block border-t border-neutral-200 px-4 py-2.5 text-center text-xs font-medium text-blue-600 hover:bg-neutral-50 dark:border-neutral-700 dark:text-blue-400 dark:hover:bg-neutral-800">
            Voir toutes les notifications
        </a>
    </div>
</flux:dropdown>
