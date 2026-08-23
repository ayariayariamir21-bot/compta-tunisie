@extends('pdf.layout')

@section('content')
    <table class="summary-table">
        <tr>
            <td class="label">TVA collectée</td>
            <td class="num">{{ \App\Support\PdfFormat::money($summary['collected_vat']) }}</td>
        </tr>
        <tr>
            <td class="label">TVA déductible</td>
            <td class="num">{{ \App\Support\PdfFormat::money($summary['deductible_vat']) }}</td>
        </tr>
        <tr>
            <td class="label">TVA nette</td>
            <td class="num">{{ \App\Support\PdfFormat::money($summary['net_vat']) }} ({{ $summary['status_label'] }})</td>
        </tr>
        <tr>
            <td class="label">Base taxable — ventes</td>
            <td class="num">{{ \App\Support\PdfFormat::money($summary['total_output_base']) }}</td>
        </tr>
        <tr>
            <td class="label">Base taxable — achats</td>
            <td class="num">{{ \App\Support\PdfFormat::money($summary['total_input_base']) }}</td>
        </tr>
    </table>

    @php($collecteeRows = array_values(array_filter($report['tax_rates'], fn (array $row): bool => $row['direction'] === 'collectee')))
    @php($deductibleRows = array_values(array_filter($report['tax_rates'], fn (array $row): bool => $row['direction'] === 'deductible')))

    <h2 class="section">TVA collectée</h2>

    @if (count($collecteeRows) === 0)
        <p class="empty-state">Aucune donnée pour la période sélectionnée.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th class="num">Taux (%)</th>
                    <th class="num">Base HT</th>
                    <th class="num">TVA</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($collecteeRows as $row)
                    <tr>
                        <td>{{ $row['code'] }}</td>
                        <td>{{ $row['type_label'] }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money((string) $row['rate']) }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money((string) $row['base']) }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money((string) $row['vat']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2 class="section">TVA déductible</h2>

    @if (count($deductibleRows) === 0)
        <p class="empty-state">Aucune donnée pour la période sélectionnée.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th class="num">Taux (%)</th>
                    <th class="num">Base HT</th>
                    <th class="num">TVA</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($deductibleRows as $row)
                    <tr>
                        <td>{{ $row['code'] }}</td>
                        <td>{{ $row['type_label'] }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money((string) $row['rate']) }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money((string) $row['base']) }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money((string) $row['vat']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2 class="section">Détail des documents</h2>

    @if (count($report['documents']) === 0)
        <p class="empty-state">Aucune donnée pour la période sélectionnée.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Document</th>
                    <th>Tiers</th>
                    <th>Code TVA</th>
                    <th class="num">Base HT</th>
                    <th class="num">TVA</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['documents'] as $document)
                    <tr>
                        <td>{{ \App\Support\PdfFormat::dateFr((string) $document['date']) }}</td>
                        <td>{{ $document['document_type_label'] }}</td>
                        <td>{{ $document['document_number'] }}</td>
                        <td>{{ $document['party'] }}</td>
                        <td>{{ $document['code'] }} @ {{ \App\Support\PdfFormat::money((string) $document['rate']) }} %</td>
                        <td class="num">{{ \App\Support\PdfFormat::money((string) $document['base']) }}</td>
                        <td class="num">{{ \App\Support\PdfFormat::money((string) $document['vat']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (bccomp($report['unattributed_collected'], '0.000', 3) !== 0 || bccomp($report['unattributed_deductible'], '0.000', 3) !== 0)
        <div class="note-box">
            Autres mouvements TVA (écritures manuelles sans pièce associée) :
            collectée {{ \App\Support\PdfFormat::money($report['unattributed_collected']) }},
            déductible {{ \App\Support\PdfFormat::money($report['unattributed_deductible']) }}.
        </div>
    @endif

    <p class="disclaimer">
        Rapport comptable interne établi à partir des écritures postées — ne constitue pas une déclaration officielle de TVA.
    </p>
@endsection
