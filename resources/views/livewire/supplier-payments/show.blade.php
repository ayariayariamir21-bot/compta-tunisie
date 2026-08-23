<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Règlement {{ $payment->payment_number }}</h1>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
            {{ $payment->status->color() }}">
            {{ $payment->status->label() }}
        </span>
    </div>

    @if (session('success'))
        <div class="rounded-md bg-green-50 p-4 dark:bg-green-900/20">
            <p class="text-sm text-green-700 dark:text-green-400">{{ session('success') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-md bg-red-50 p-4 dark:bg-red-900/20">
            <p class="text-sm text-red-700 dark:text-red-400">{{ session('error') }}</p>
        </div>
    @endif

    <div class="flux-card grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Date de paiement</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $payment->payment_date->format('d/m/Y') }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Fournisseur</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                @if ($payment->supplier)
                    <a href="{{ route('suppliers.show', $payment->supplier_id) }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>{{ $payment->supplier->name }}</a>
                @else
                    —
                @endif
            </p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Moyen de paiement</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $payment->paymentMethod?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Référence</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $payment->reference ?? '—' }}</p>
        </div>
    </div>

    <div class="flux-card">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Imputations</h2>
        @if ($payment->allocations->count() === 0)
            <p class="text-sm italic text-gray-500 dark:text-gray-400">Aucune imputation — avance fournisseur.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Statut</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Montant imputé</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach($payment->allocations as $allocation)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    @if ($allocation->purchaseInvoice)
                                        <a href="{{ route('purchase-invoices.show', $allocation->purchase_invoice_id) }}" class="font-medium text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>{{ $allocation->purchaseInvoice->invoice_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $allocation->purchaseInvoice?->status?->label() ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ number_format((float) $allocation->amount, 3, '.', '') }} {{ $payment->currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="mt-6 flex flex-col items-end space-y-1 border-t border-gray-200 pt-4 text-sm dark:border-gray-700">
            <div class="flex w-full max-w-xs justify-between">
                <span class="text-gray-500 dark:text-gray-400">Total imputé</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ number_format((float) $payment->allocatedAmount(), 3, '.', '') }} {{ $payment->currency }}</span>
            </div>
            <div class="flex w-full max-w-xs justify-between border-t border-gray-200 pt-2 text-base dark:border-gray-700">
                <span class="font-semibold text-gray-900 dark:text-white">Non imputé</span>
                <span class="font-bold {{ bccomp($unallocated, '0', 3) > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-white' }}">{{ number_format((float) $unallocated, 3, '.', '') }} {{ $payment->currency }}</span>
            </div>
        </div>
    </div>

    <div class="flux-card">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Comptabilisation</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Journal</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $payment->journal?->code ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Compte de destination</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $payment->destinationAccount ? $payment->destinationAccount->code.' — '.$payment->destinationAccount->name : '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Période</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $payment->accountingPeriod?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Écriture comptable</p>
                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                    @if ($payment->journalEntry)
                        <a href="{{ route('journal-entries.show', $payment->journalEntry->id) }}" class="text-flux-600 hover:text-flux-500 dark:text-flux-400" wire:navigate>{{ $payment->journalEntry->entry_number }}</a>
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>

        @if ($payment->journalEntry)
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Compte</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Libellé</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Débit</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Crédit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach($payment->journalEntry->lines as $line)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $line->account?->code ?? '—' }} — {{ $line->account?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $line->description }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ ((float) $line->debit) > 0 ? number_format((float) $line->debit, 3, '.', '') : '' }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                    {{ ((float) $line->credit) > 0 ? number_format((float) $line->credit, 3, '.', '') : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($payment->notes)
        <div class="flux-card">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Notes</h2>
            <p class="text-sm whitespace-pre-line text-gray-500 dark:text-gray-400">{{ $payment->notes }}</p>
        </div>
    @endif

    <div class="flex justify-end gap-3">
        @if ($payment->status->value === 'draft')
            <a href="{{ route('supplier-payments.edit', $payment->id) }}" class="flux-btn-secondary" wire:navigate>Modifier</a>
            <button wire:click="cancel" wire:confirm="Voulez-vous vraiment annuler ce règlement ?" class="rounded-md border border-orange-300 px-4 py-2 text-sm font-medium text-orange-600 hover:bg-orange-50 dark:border-orange-700 dark:hover:bg-orange-900/20">Annuler le règlement</button>
            <button wire:click="delete" wire:confirm="Voulez-vous vraiment supprimer ce règlement ? Cette action est irréversible." class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-900/20">Supprimer</button>
            <button wire:click="post" wire:confirm="Voulez-vous vraiment comptabiliser ce règlement ? Cette action est irréversible." class="flux-btn-primary">Comptabiliser</button>
        @elseif ($payment->status->value === 'posted')
            <p class="text-sm italic text-gray-500 dark:text-gray-400">Règlement comptabilisé — document immuable.</p>
        @else
            <p class="text-sm italic text-gray-500 dark:text-gray-400">Règlement annulé.</p>
        @endif
    </div>
</div>
