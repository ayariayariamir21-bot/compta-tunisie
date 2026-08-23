@extends('pdf.layout')

@section('content')
    <h2 class="section">Produits</h2>

    @if (count($report['revenues']) === 0)
        <p class="empty-state">Aucune donnée pour la période sélectionnée.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Compte</th>
                    <th class="num">Montant</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['revenues'] as $row)
                    <tr @class(['group-row' => $row['is_group']])>
                        <td>{{ $row['code'] }} — {{ $row['name'] }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money($row['is_group'] ? $row['subtotal'] : $row['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total produits</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($summary['total_revenue']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <h2 class="section">Charges</h2>

    @if (count($report['expenses']) === 0)
        <p class="empty-state">Aucune donnée pour la période sélectionnée.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Compte</th>
                    <th class="num">Montant</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['expenses'] as $row)
                    <tr @class(['group-row' => $row['is_group']])>
                        <td>{{ $row['code'] }} — {{ $row['name'] }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money($row['is_group'] ? $row['subtotal'] : $row['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total charges</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($summary['total_expenses']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <h2 class="section">Résultat</h2>

    <table class="summary-table">
        <tr>
            <td class="label">Total produits</td>
            <td class="num">{{ \App\Support\PdfFormat::money($summary['total_revenue']) }}</td>
        </tr>
        <tr>
            <td class="label">− Total charges</td>
            <td class="num">{{ \App\Support\PdfFormat::money($summary['total_expenses']) }}</td>
        </tr>
        <tr>
            <td class="label">= Résultat net {{ $summary['has_profit'] ? '(Bénéfice)' : ($summary['has_loss'] ? '(Perte)' : '(Résultat nul)') }}</td>
            <td class="num">{{ \App\Support\PdfFormat::money($summary['net_result']) }}</td>
        </tr>
    </table>
@endsection
