<x-layouts::app :title="__('Devis : ' . $quote->quote_number)">
    <div class="flex h-full w-full flex-1 flex-col gap-6">

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ $quote->quote_number }}</h1>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Devis du {{ $quote->quote_date->format('d/m/Y') }} — {{ $quote->customer->name ?? 'Client inconnu' }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if (Auth::user()->can('update', $quote))
                    <a href="{{ route('quotes.edit', $quote->id) }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-lg border border-neutral-200 px-4 py-2.5 text-sm font-medium transition hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                        Modifier
                    </a>
                @endif

                @if (Auth::user()->can('send', $quote))
                    <button wire:click="transitionTo('send')" wire:confirm="Envoyer ce devis ?"
                        class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">
                        Envoyer
                    </button>
                @endif

                @if (Auth::user()->can('accept', $quote))
                    <button wire:click="transitionTo('accept')" wire:confirm="Accepter ce devis ?"
                        class="inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-700">
                        Accepter
                    </button>
                @endif

                @if (Auth::user()->can('reject', $quote))
                    <button wire:click="transitionTo('reject')" wire:confirm="Refuser ce devis ?"
                        class="inline-flex items-center justify-center rounded-lg border border-red-300 px-4 py-2.5 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950">
                        Refuser
                    </button>
                @endif

                @if (Auth::user()->can('cancel', $quote))
                    <button wire:click="transitionTo('cancel')" wire:confirm="Annuler ce devis ?"
                        class="inline-flex items-center justify-center rounded-lg border border-amber-300 px-4 py-2.5 text-sm font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-700 dark:text-amber-400 dark:hover:bg-amber-950">
                        Annuler
                    </button>
                @endif

                <a href="{{ route('quotes.index') }}" wire:navigate
                    class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
                    Retour
                </a>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Quote Info --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">Informations</h2>
                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Numéro</dt>
                        <dd class="font-medium">{{ $quote->quote_number }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Statut</dt>
                        <dd>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ match($quote->status) {
                                    \App\Enums\QuoteStatus::DRAFT => 'bg-neutral-100 text-neutral-600',
                                    \App\Enums\QuoteStatus::SENT => 'bg-blue-100 text-blue-800',
                                    \App\Enums\QuoteStatus::ACCEPTED => 'bg-green-100 text-green-800',
                                    \App\Enums\QuoteStatus::REJECTED => 'bg-red-100 text-red-800',
                                    \App\Enums\QuoteStatus::EXPIRED => 'bg-amber-100 text-amber-800',
                                    \App\Enums\QuoteStatus::CANCELLED => 'bg-neutral-100 text-neutral-500',
                                    default => 'bg-neutral-100',
                                }}">
                                {{ $quote->status->label() }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Date</dt>
                        <dd class="font-medium">{{ $quote->quote_date->format('d/m/Y') }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Validité</dt>
                        <dd class="font-medium">{{ $quote->valid_until ? $quote->valid_until->format('d/m/Y') : '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Devise</dt>
                        <dd class="font-medium">{{ $quote->currency }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Créé par</dt>
                        <dd class="font-medium">{{ $quote->creator->name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Customer --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">Client</h2>
                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Nom</dt>
                        <dd class="font-medium">{{ $quote->customer->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Raison sociale</dt>
                        <dd class="font-medium">{{ $quote->customer->legal_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Identifiant fiscal</dt>
                        <dd class="font-medium">{{ $quote->customer->tax_identifier ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Adresse</dt>
                        <dd class="font-medium text-right">{{ $quote->customer->address ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Ville</dt>
                        <dd class="font-medium">{{ $quote->customer->city ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Email</dt>
                        <dd class="font-medium">{{ $quote->customer->email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Téléphone</dt>
                        <dd class="font-medium">{{ $quote->customer->phone ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Totals --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">Totaux</h2>
                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Total HT</dt>
                        <dd class="font-medium">{{ number_format((float) $quote->subtotal, 3, ',', '.') }} {{ $quote->currency }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Remise</dt>
                        <dd class="font-medium text-red-600">-{{ number_format((float) $quote->discount_total, 3, ',', '.') }} {{ $quote->currency }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">TVA</dt>
                        <dd class="font-medium">{{ number_format((float) $quote->tax_total, 3, ',', '.') }} {{ $quote->currency }}</dd>
                    </div>
                    <div class="border-t border-neutral-200 pt-3 dark:border-neutral-700">
                        <div class="flex justify-between text-base font-semibold">
                            <dt>Total TTC</dt>
                            <dd>{{ number_format((float) $quote->total, 3, ',', '.') }} {{ $quote->currency }}</dd>
                        </div>
                    </div>
                </dl>

                <div class="mt-6 space-y-3">
                    <div class="flex justify-between text-sm">
                        <dt class="text-neutral-500 dark:text-neutral-400">Conditions de paiement</dt>
                        <dd class="font-medium">{{ $quote->payment_terms_days }} jours</dd>
                    </div>
                </div>
            </div>
        </div>

        {{-- Lines --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <h2 class="text-lg font-semibold">Lignes du devis</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-neutral-500 dark:text-neutral-400">#</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-neutral-500 dark:text-neutral-400">Produit</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-neutral-500 dark:text-neutral-400">Description</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase text-neutral-500 dark:text-neutral-400">Qté</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-neutral-500 dark:text-neutral-400">Unité</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase text-neutral-500 dark:text-neutral-400">Prix unit.</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase text-neutral-500 dark:text-neutral-400">Remise</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-neutral-500 dark:text-neutral-400">TVA</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase text-neutral-500 dark:text-neutral-400">Montant TVA</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase text-neutral-500 dark:text-neutral-400">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($quote->lines as $index => $line)
                            <tr class="transition hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                <td class="whitespace-nowrap px-3 py-2 text-sm text-neutral-500">{{ $index + 1 }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-sm font-medium">{{ $line->product->code ?? '—' }}</td>
                                <td class="px-3 py-2 text-sm">{{ $line->description }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-sm">{{ number_format((float) $line->quantity, 3, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-sm text-neutral-500">{{ $line->unit }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-sm">{{ number_format((float) $line->unit_price, 3, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-sm">
                                    @if ((float) $line->discount_percent > 0)
                                        {{ number_format((float) $line->discount_percent, 3, ',', '.') }}%
                                        <span class="text-xs text-red-500">(-{{ number_format((float) $line->discount_amount, 3, ',', '.') }})</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-sm text-neutral-500">
                                    {{ $line->taxRate ? $line->taxRate->name : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-sm">{{ number_format((float) $line->tax_amount, 3, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-sm font-medium">{{ number_format((float) $line->line_total, 3, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Notes & Terms --}}
        @if ($quote->notes || $quote->terms)
            <div class="grid gap-6 lg:grid-cols-2">
                @if ($quote->notes)
                    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <h2 class="text-lg font-semibold">Notes internes</h2>
                        <p class="mt-3 text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-line">{{ $quote->notes }}</p>
                    </div>
                @endif

                @if ($quote->terms)
                    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <h2 class="text-lg font-semibold">Conditions générales</h2>
                        <p class="mt-3 text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-line">{{ $quote->terms }}</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-layouts::app>
