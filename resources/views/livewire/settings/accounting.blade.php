<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">Paramètres comptables</flux:heading>

    <x-pages::settings.layout :heading="__('Paramètres comptables')" :subheading="__('Configurer les paramètres comptables de l\'entreprise')">

        @if (! $currentCompany)
            <flux:text class="my-6">
                Aucune entreprise active. Veuillez sélectionner une entreprise pour configurer ses paramètres comptables.
            </flux:text>
        @else
            <form wire:submit="save" class="my-6 w-full space-y-8">

                {{-- Général --}}
                <div class="space-y-4">
                    <flux:heading size="sm">Général</flux:heading>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input
                            wire:model="default_currency"
                            label="Devise"
                            type="text"
                            maxlength="3"
                            required
                        />

                        <flux:select wire:model="decimal_precision" label="Précision décimale">
                            <flux:select.option value="0">0</flux:select.option>
                            <flux:select.option value="1">1</flux:select.option>
                            <flux:select.option value="2">2</flux:select.option>
                            <flux:select.option value="3">3</flux:select.option>
                            <flux:select.option value="4">4</flux:select.option>
                            <flux:select.option value="5">5</flux:select.option>
                            <flux:select.option value="6">6</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                <flux:separator />

                {{-- Exercice --}}
                <div class="space-y-4">
                    <flux:heading size="sm">Exercice</flux:heading>

                    <flux:select wire:model="fiscal_year_start_month" label="Mois de début de l'exercice">
                        <flux:select.option value="1">Janvier</flux:select.option>
                        <flux:select.option value="2">Février</flux:select.option>
                        <flux:select.option value="3">Mars</flux:select.option>
                        <flux:select.option value="4">Avril</flux:select.option>
                        <flux:select.option value="5">Mai</flux:select.option>
                        <flux:select.option value="6">Juin</flux:select.option>
                        <flux:select.option value="7">Juillet</flux:select.option>
                        <flux:select.option value="8">Août</flux:select.option>
                        <flux:select.option value="9">Septembre</flux:select.option>
                        <flux:select.option value="10">Octobre</flux:select.option>
                        <flux:select.option value="11">Novembre</flux:select.option>
                        <flux:select.option value="12">Décembre</flux:select.option>
                    </flux:select>
                </div>

                <flux:separator />

                {{-- Journaux par défaut --}}
                <div class="space-y-4">
                    <flux:heading size="sm">Journaux par défaut</flux:heading>

                    @if (! $currentFiscalYear)
                        <flux:text class="text-zinc-500">
                            Aucun exercice actif. Les journaux par défaut ne peuvent pas être sélectionnés sans exercice.
                        </flux:text>
                    @else
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:select wire:model="default_sales_journal_id" label="Journal des ventes">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($journals as $journal)
                                    <flux:select.option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="default_purchase_journal_id" label="Journal des achats">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($journals as $journal)
                                    <flux:select.option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="default_bank_journal_id" label="Journal banque">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($journals as $journal)
                                    <flux:select.option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="default_cash_journal_id" label="Journal caisse">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($journals as $journal)
                                    <flux:select.option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="default_misc_journal_id" label="Journal opérations diverses">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($journals as $journal)
                                    <flux:select.option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                </div>

                <flux:separator />

                {{-- Comptes par défaut --}}
                <div class="space-y-4">
                    <flux:heading size="sm">Comptes par défaut</flux:heading>

                    @if (! $currentFiscalYear)
                        <flux:text class="text-zinc-500">
                            Aucun exercice actif. Les comptes par défaut ne peuvent pas être sélectionnés sans exercice.
                        </flux:text>
                    @else
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:select wire:model="default_customer_account_id" label="Compte clients">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($accounts as $account)
                                    <flux:select.option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="default_supplier_account_id" label="Compte fournisseurs">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($accounts as $account)
                                    <flux:select.option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="default_sales_account_id" label="Compte ventes">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($accounts as $account)
                                    <flux:select.option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="default_purchase_account_id" label="Compte achats">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($accounts as $account)
                                    <flux:select.option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="default_bank_account_id" label="Compte banque">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($accounts as $account)
                                    <flux:select.option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="default_cash_account_id" label="Compte caisse">
                                <flux:select.option value="">— Aucun —</flux:select.option>
                                @foreach ($accounts as $account)
                                    <flux:select.option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                </div>

                <flux:separator />

                {{-- Numérotation --}}
                <div class="space-y-4">
                    <flux:heading size="sm">Numérotation</flux:heading>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input
                            wire:model="invoice_prefix"
                            label="Préfixe facture"
                            type="text"
                            maxlength="20"
                        />

                        <flux:input
                            wire:model="invoice_next_number"
                            label="Prochain numéro facture"
                            type="number"
                            min="1"
                            required
                        />

                        <flux:input
                            wire:model="quote_prefix"
                            label="Préfixe devis"
                            type="text"
                            maxlength="20"
                        />

                        <flux:input
                            wire:model="quote_next_number"
                            label="Prochain numéro devis"
                            type="number"
                            min="1"
                            required
                        />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" class="w-full sm:w-auto">
                        Enregistrer
                    </flux:button>
                </div>
            </form>
        @endif

    </x-pages::settings.layout>
</section>
