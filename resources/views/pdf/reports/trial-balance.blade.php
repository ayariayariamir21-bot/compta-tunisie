@extends('pdf.layout')

@section('content')
    @if (count($trialBalance['accounts']) === 0)
        <p class="empty-state">Aucune donnée pour la période sélectionnée.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Nom du compte</th>
                    <th>Type</th>
                    <th class="num">Débit</th>
                    <th class="num">Crédit</th>
                    <th class="num">Solde débit</th>
                    <th class="num">Solde crédit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($trialBalance['accounts'] as $row)
                    <tr>
                        <td>{{ $row->code }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ \App\Support\PdfFormat::accountType($row->account_type) }}</td>
                        <td class="num">{{ bccomp((string) $row->total_debit, '0.000', 3) !== 0 ? \App\Support\PdfFormat::money((string) $row->total_debit) : '' }}</td>
                        <td class="num">{{ bccomp((string) $row->total_credit, '0.000', 3) !== 0 ? \App\Support\PdfFormat::money((string) $row->total_credit) : '' }}</td>
                        <td class="num">{{ bccomp($row->debit_balance, '0.000', 3) !== 0 ? \App\Support\PdfFormat::money($row->debit_balance) : '' }}</td>
                        <td class="num">{{ bccomp($row->credit_balance, '0.000', 3) !== 0 ? \App\Support\PdfFormat::money($row->credit_balance) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Totaux</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($trialBalance['total_debit']) }}</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($trialBalance['total_credit']) }}</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($trialBalance['total_debit_balance']) }}</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($trialBalance['total_credit_balance']) }}</td>
                </tr>
            </tfoot>
        </table>

        @if ($trialBalance['is_balanced'])
            <div class="status-ok">Balance équilibrée</div>
        @else
            <div class="status-error">Écart détecté — Différence : {{ \App\Support\PdfFormat::money($trialBalance['difference']) }}</div>
        @endif
    @endif
@endsection
