@extends('pdf.layout')

@section('content')
    <table class="summary-table">
        <tr>
            <td class="label">Fournisseur</td>
            <td>{{ $supplier->code }} — {{ $supplier->name }}</td>
        </tr>
        @if ($supplier->tax_identifier !== null && $supplier->tax_identifier !== '')
            <tr>
                <td class="label">Identifiant fiscal</td>
                <td>{{ $supplier->tax_identifier }}</td>
            </tr>
        @endif
    </table>

    @php($rowValueDebit = fn (string $value): string => bccomp($value, '0.000', 3) > 0 ? \App\Support\PdfFormat::money($value) : '')
    @php($rowValueCredit = fn (string $value): string => bccomp($value, '0.000', 3) > 0 ? \App\Support\PdfFormat::money($value) : '')

    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Document</th>
                <th>N° facture fourn.</th>
                <th>Référence</th>
                <th>Description</th>
                <th class="num">Débit</th>
                <th class="num">Crédit</th>
                <th class="num">Solde</th>
            </tr>
        </thead>
        <tbody>
            <tr class="section-row">
                <td colspan="6">Solde d'ouverture</td>
                <td class="num"></td>
                <td class="num"></td>
                <td class="num">{{ \App\Support\PdfFormat::money($statement['opening_balance']) }}</td>
            </tr>

            @forelse ($statement['entries'] as $entry)
                <tr>
                    <td>{{ \App\Support\PdfFormat::dateFr($entry['date']) }}</td>
                    <td>{{ \App\Support\PdfFormat::statementType($entry['type'], 'supplier') }}</td>
                    <td>{{ $entry['document_number'] }}</td>
                    <td>{{ $entry['supplier_invoice_number'] ?? '—' }}</td>
                    <td>{{ $entry['reference'] ?? '—' }}</td>
                    <td>{{ $entry['description'] ?? '' }}</td>
                    <td class="num">{{ $rowValueDebit($entry['debit']) }}</td>
                    <td class="num">{{ $rowValueCredit($entry['credit']) }}</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($entry['balance']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center; color:#6b7280; font-style:italic;">
                        Aucune donnée pour la période sélectionnée.
                    </td>
                </tr>
            @endforelse

            <tr class="totals">
                <td colspan="6">Totaux — Solde de clôture</td>
                <td class="num">{{ $rowValueDebit($statement['total_debit']) }}</td>
                <td class="num">{{ $rowValueCredit($statement['total_credit']) }}</td>
                <td class="num">{{ \App\Support\PdfFormat::money($statement['closing_balance']) }}</td>
            </tr>
        </tbody>
    </table>

    @if (bccomp((string) $statement['closing_balance'], '0.000', 3) === -1)
        <div class="status-error">Le fournisseur dispose d'un solde créditeur (dette).</div>
    @elseif (bccomp((string) $statement['closing_balance'], '0.000', 3) === 1)
        <div class="status-ok">Le fournisseur dispose d'un solde débiteur (trop-perçu).</div>
    @else
        <div class="status-ok">Le compte du fournisseur est soldé.</div>
    @endif
@endsection
