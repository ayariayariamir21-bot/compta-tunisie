@extends('pdf.layout')

@section('content')
    @php($rowValue = fn (array $row): string => $row['is_group'] ? $row['subtotal'] : $row['balance'])

    <h2 class="section">Actif</h2>

    @if (count($report['assets']) === 0)
        <p class="empty-state">Aucune donnée pour la période sélectionnée.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Compte</th>
                    <th class="num">Solde</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['assets'] as $row)
                    <tr @class(['group-row' => $row['is_group']])>
                        <td>{{ $row['code'] }} — {{ $row['name'] }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money($rowValue($row)) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>TOTAL ACTIF</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($summary['total_assets']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <h2 class="section">Passif &amp; Capitaux propres</h2>

    @if (count($report['liabilities']) === 0 && count($report['equity']) === 0 && bccomp($report['result']['value'], '0.000', 3) === 0)
        <p class="empty-state">Aucune donnée pour la période sélectionnée.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Compte</th>
                    <th class="num">Solde</th>
                </tr>
            </thead>
            <tbody>
                @if (count($report['equity']) > 0)
                    <tr class="section-row">
                        <td colspan="2">Capitaux propres</td>
                    </tr>
                    @foreach ($report['equity'] as $row)
                        <tr @class(['group-row' => $row['is_group']])>
                            <td>{{ $row['code'] }} — {{ $row['name'] }}</td>
                            <td class="num">{{ \App\Support\PdfFormat::money($rowValue($row)) }}</td>
                        </tr>
                    @endforeach
                @endif

                <tr>
                    <td>Résultat de l'exercice {{ $summary['has_profit'] ? '(Bénéfice)' : '(Perte)' }}</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($report['result']['value']) }}</td>
                </tr>

                @if (count($report['liabilities']) > 0)
                    <tr class="section-row">
                        <td colspan="2">Dettes</td>
                    </tr>
                    @foreach ($report['liabilities'] as $row)
                        <tr @class(['group-row' => $row['is_group']])>
                            <td>{{ $row['code'] }} — {{ $row['name'] }}</td>
                            <td class="num">{{ \App\Support\PdfFormat::money($rowValue($row)) }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
            <tfoot>
                <tr>
                    <td>Total Passif</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($summary['total_liabilities']) }}</td>
                </tr>
                <tr>
                    <td>TOTAL PASSIF + CAPITAUX PROPRES</td>
                    <td class="num">{{ \App\Support\PdfFormat::money($summary['total_passif_capitaux']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    @if ($summary['is_balanced'])
        <div class="status-ok">Le bilan est équilibré — Total Actif = Total Passif + Capitaux propres ({{ \App\Support\PdfFormat::money($summary['total_passif_capitaux']) }})</div>
    @else
        <div class="status-error">Le bilan présente un écart de {{ \App\Support\PdfFormat::money($summary['difference']) }} entre le Total Actif et le Total Passif + Capitaux propres.</div>
    @endif
@endsection
