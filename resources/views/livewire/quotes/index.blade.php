<x-layouts::app :title="__('Devis')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Devis</h1>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    @if ($currentCompany)
                        Devis de « {{ $currentCompany->name }} ».
                    @else
                        Sélectionnez une société pour voir les devis.
                    @endif
                </p>
            </div>

            @if ($currentCompany)
                <div class="flex items-center gap-2">
                    <a href="{{ route('quotes.create') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                        + Nouveau devis
                    </a>
                </div>
            @endif
        </div>

        @if (session()->has('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if (! $currentCompany)
            <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <h2 class="text-lg font-semibold">Aucune société sélectionnée</h2>
                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">Sélectionnez une société dans le menu latéral.</p>
            </div>
        @else
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:flex-wrap">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Rechercher par numéro, notes..."
                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800 sm:w-96">

                <select wire:model.live="filterStatus" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">Tous les statuts</option>
                    @foreach ($quoteStatuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterCustomerId" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">Tous les clients</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>

                <input type="date" wire:model.live="filterDateFrom" placeholder="Du..."
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">

                <input type="date" wire:model.live="filterDateTo" placeholder="Au..."
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
            </div>

            @if ($quotes->isEmpty())
                <div class="rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                    <h2 class="text-lg font-semibold">Aucun devis</h2>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">Créez un nouveau devis pour commencer.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Numéro</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Client</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Statut</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Validité</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Total TTC</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Devise</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($quotes as $quote)
                                <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium">
                                        <a href="{{ route('quotes.show', $quote->id) }}" wire:navigate class="text-neutral-900 hover:underline dark:text-neutral-100">
                                            {{ $quote->quote_number }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">
                                        {{ $quote->quote_date->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ $quote->customer->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                            {{ match($quote->status) {
                                                \App\Enums\QuoteStatus::DRAFT => 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400',
                                                \App\Enums\QuoteStatus::SENT => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                \App\Enums\QuoteStatus::ACCEPTED => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                \App\Enums\QuoteStatus::REJECTED => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                \App\Enums\QuoteStatus::EXPIRED => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                                \App\Enums\QuoteStatus::CANCELLED => 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-500',
                                                default => 'bg-neutral-100 text-neutral-600',
                                            }}">
                                            {{ $quote->status->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">
                                        {{ $quote->valid_until ? $quote->valid_until->format('d/m/Y') : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium">
                                        {{ number_format((float) $quote->total, 3, ',', '.') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-neutral-500">{{ $quote->currency }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('quotes.show', $quote->id) }}" wire:navigate class="rounded-md px-2 py-1 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800">Voir</a>

                                            @if (Auth::user()->can('update', $quote))
                                                <a href="{{ route('quotes.edit', $quote->id) }}" wire:navigate class="rounded-md px-2 py-1 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800">Modifier</a>
                                            @endif

                                            @if (Auth::user()->can('send', $quote))
                                                <button wire:click="transitionTo({{ $quote->id }}, 'send')" wire:confirm="Envoyer ce devis ?"
                                                    class="rounded-md px-2 py-1 text-sm text-blue-600 transition hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950">Envoyer</button>
                                            @endif

                                            @if (Auth::user()->can('accept', $quote))
                                                <button wire:click="transitionTo({{ $quote->id }}, 'accept')" wire:confirm="Accepter ce devis ?"
                                                    class="rounded-md px-2 py-1 text-sm text-green-600 transition hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-950">Accepter</button>
                                            @endif

                                            @if (Auth::user()->can('reject', $quote))
                                                <button wire:click="transitionTo({{ $quote->id }}, 'reject')" wire:confirm="Refuser ce devis ?"
                                                    class="rounded-md px-2 py-1 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950">Refuser</button>
                                            @endif

                                            @if (Auth::user()->can('cancel', $quote))
                                                <button wire:click="transitionTo({{ $quote->id }}, 'cancel')" wire:confirm="Annuler ce devis ?"
                                                    class="rounded-md px-2 py-1 text-sm text-amber-600 transition hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950">Annuler</button>
                                            @endif

                                            @if (Auth::user()->can('delete', $quote))
                                                <button wire:click="delete({{ $quote->id }})" wire:confirm="Supprimer le devis « {{ $quote->quote_number }} » ?"
                                                    class="rounded-md px-2 py-1 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950">Supprimer</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">{{ $quotes->links() }}</div>
            @endif
        @endif
    </div>
</x-layouts::app>
