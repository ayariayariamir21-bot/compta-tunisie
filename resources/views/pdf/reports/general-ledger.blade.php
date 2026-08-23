@extends('pdf.layout')

@section('content')
    @if ($accountFilter !== null)
        <p class="period-line">Compte filtré : {{ $accountFilter->code }} — {{ $accountFilter->name }}</p>
    @endif

    @forelse ($ledgerData as $summary)
        <h2 class="section">{{ $summary['account']->code }} — {{ $summary['account']->name }}</h2>

        <table class="data">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Journal</th>
                    <th>N° écriture</th>
                    <th>Réf.</th>
                    <th>Description</th>
                    <th class="num">Débit</th>
                    <th class="num">Crédit</th>
                    <th class="num">Solde</th>
                </tr>
            </thead>
            <tbody>
                @if ($summary['opening_balance'] !== '0.000')
                    <tr class="section-row">
                        <td colspan="5">Solde d'ouverture</td>
                        <td class="num"></td>
                        <td class="num"></td>
                        <td class="num">{{ \App\Support\PdfFormat::money($summary['opening_balance']) }}</td>
                    </tr>
                @endif

                @php($totalDebit = '0.000')
                @php($totalCredit = '0.000')
                @php($runningBalance = $summary['opening_balance'])

                @foreach ($summary['lines'] as $line)
                    @php($lineDebit = (string) $line->getAttribute('debit'))
                    @php($lineCredit = (string) $line->getAttribute('credit'))
                    @php($totalDebit = bcadd($totalDebit, $lineDebit, 3))
                    @php($totalCredit = bcadd($totalCredit, $lineCredit, 3))
                    @php($runningBalance = bcsub(bcadd($runningBalance, $lineDebit, 3), $lineCredit, 3))

                    <tr>
                        <td>{{ \App\Support\PdfFormat::dateFr((string) $line->getAttribute('entry_date')) }}</td>
                        <td>{{ $line->getAttribute('journal_code') }}</td>
                        <td>{{ $line->getAttribute('entry_number') }}</td>
                        <td>{{ $line->getAttribute('reference') ?? '—' }}</td>
                        <td>{{ $line->getAttribute('entry_description') ?? ($line->getAttribute('line_description') ?? '—') }}</td>
                        <td class="num">{{ bccomp($lineDebit, '0.000', 3) > 0 ? \App\Support\PdfFormat::money($lineDebit) : '' }}</td>
                        <td class="num">{{ bccomp($lineCredit, '0.000', 3) > 0 ? \App\Support\PdfFormat::money($lineCredit) : '' }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money($runningBalance) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">Total des mouvements — Solde de clôture</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($totalDebit) }}</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($totalCredit) }}</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($summary['closing_balance']) }}</td>
                </tr>
            </tfoot>
        </table>
    @empty
        <p class="empty-state">Aucune donnée pour la période sélectionnée.</p>
    @endforelse
@endsection
