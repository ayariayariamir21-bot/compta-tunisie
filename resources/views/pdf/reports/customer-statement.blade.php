@extends('pdf.layout')

@section('content')
    <table class="summary-table">
        <tr>
            <td class="label">Client</td>
            <td>{{ $customer->code }} — {{ $customer->name }}</td>
        </tr>
        @if ($customer->tax_identifier !== null && $customer->tax_identifier !== '')
            <tr>
                <td class="label">Identifiant fiscal</td>
                <td>{{ $customer->tax_identifier }}</td>
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
                <th>Référence</th>
                <th>Description</th>
                <th class="num">Débit</th>
                <th class="num">Crédit</th>
                <th class="num">Solde</th>
            </tr>
        </thead>
        <tbody>
            <tr class="section-row">
                <td colspan="5">Solde d'ouverture</td>
                <td class="num"></td>
                <td class="num"></td>
                <td class="num">{{ \App\Support\PdfFormat::money($statement['opening_balance']) }}</td>
            </tr>

            @forelse ($statement['entries'] as $entry)
                <tr>
                    <td>{{ \App\Support\PdfFormat::dateFr($entry['date']) }}</td>
                    <td>{{ \App\Support\PdfFormat::statementType($entry['type'], 'customer') }}</td>
                    <td>{{ $entry['document_number'] }}</td>
                    <td>{{ $entry['reference'] ?? '—' }}</td>
                    <td>{{ $entry['description'] ?? '' }}</td>
                    <td class="num">{{ $rowValueDebit($entry['debit']) }}</td>
                    <td class="num">{{ $rowValueCredit($entry['credit']) }}</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($entry['balance']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center; color:#6b7280; font-style:italic;">
                        Aucune donnée pour la période sélectionnée.
                    </td>
                </tr>
            @endforelse

            <tr class="totals">
                <td colspan="5">Totaux — Solde de clôture</td>
                <td class="num">{{ $rowValueDebit($statement['total_debit']) }}</td>
                <td class="num">{{ $rowValueCredit($statement['total_credit']) }}</td>
                <td class="num">{{ \App\Support\PdfFormat::money($statement['closing_balance']) }}</td>
            </tr>
        </tbody>
    </table>

    @if (bccomp((string) $statement['closing_balance'], '0.000', 3) === 1)
        <div class="status-error">Le client dispose d'un solde débiteur (créance).</div>
    @elseif (bccomp((string) $statement['closing_balance'], '0.000', 3) === -1)
        <div class="status-ok">Le client dispose d'un solde créditeur (avoir).</div>
    @else
        <div class="status-ok">Le compte du client est soldé.</div>
    @endif
@endsection
