<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $company->name }}</title>
    <style>
        @page {
            margin: 70px 36px 55px 36px;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #111111;
            margin: 0;
        }

        .report-header {
            border-bottom: 2px solid #111111;
            padding-bottom: 10px;
            margin-bottom: 14px;
            width: 100%;
        }

        .report-header td {
            vertical-align: top;
        }

        .company-block {
            font-size: 8pt;
            color: #374151;
            line-height: 1.45;
        }

        .company-name {
            font-size: 11pt;
            font-weight: bold;
            color: #111111;
        }

        .report-meta {
            text-align: right;
        }

        .report-title {
            font-size: 15pt;
            font-weight: bold;
        }

        .report-period {
            font-size: 10pt;
            color: #374151;
            margin-top: 3px;
        }

        .report-generated {
            font-size: 7.5pt;
            color: #6b7280;
            margin-top: 3px;
        }

        h2.section {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 18px 0 6px 0;
            page-break-after: avoid;
        }

        p.period-line {
            font-size: 9pt;
            color: #374151;
            margin: 0 0 12px 0;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        table.data thead {
            display: table-header-group;
        }

        table.data th {
            background-color: #f3f4f6;
            border: 0.5px solid #d1d5db;
            border-bottom: 1px solid #111111;
            padding: 4px 6px;
            font-size: 7.5pt;
            text-transform: uppercase;
            text-align: left;
        }

        table.data th.num {
            text-align: right;
        }

        table.data td {
            border-bottom: 0.5px solid #e5e7eb;
            padding: 3px 6px;
            font-size: 8.5pt;
        }

        table.data td.num {
            text-align: right;
            white-space: nowrap;
        }

        table.data tr {
            page-break-inside: avoid;
        }

        tr.section-row td {
            background-color: #f9fafb;
            font-weight: bold;
            font-size: 8pt;
            text-transform: uppercase;
            color: #374151;
        }

        tr.group-row td {
            font-weight: bold;
        }

        tfoot td, tr.totals td {
            font-weight: bold;
            background-color: #f9fafb;
            border-top: 1px solid #111111;
            border-bottom: none;
        }

        .empty-state {
            padding: 18px;
            text-align: center;
            color: #6b7280;
            font-style: italic;
            margin-bottom: 14px;
        }

        .note-box {
            background-color: #fef3c7;
            border: 0.5px solid #f59e0b;
            padding: 8px 10px;
            font-size: 8.5pt;
            margin-bottom: 14px;
        }

        .status-ok {
            background-color: #dcfce7;
            border: 0.5px solid #16a34a;
            color: #14532d;
            padding: 8px 10px;
            font-size: 8.5pt;
            font-weight: bold;
            margin-bottom: 14px;
        }

        .status-error {
            background-color: #fee2e2;
            border: 0.5px solid #dc2626;
            color: #7f1d1d;
            padding: 8px 10px;
            font-size: 8.5pt;
            font-weight: bold;
            margin-bottom: 14px;
        }

        .disclaimer {
            font-size: 7.5pt;
            color: #6b7280;
            margin-top: 20px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .summary-table td {
            padding: 5px 6px;
            font-size: 9pt;
            border-bottom: 0.5px solid #e5e7eb;
        }

        .summary-table td.num {
            text-align: right;
            white-space: nowrap;
            font-weight: bold;
        }

        .summary-table td.label {
            font-weight: bold;
        }

        footer.page-footer {
            position: fixed;
            bottom: -38px;
            left: -36px;
            right: -36px;
            text-align: center;
            font-size: 7.5pt;
            color: #9ca3af;
            border-top: 0.5px solid #e5e7eb;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <table class="report-header">
        <tr>
            <td class="company-block">
                <span class="company-name">{{ $company->name }}</span><br>
                @if ($company->legal_name !== null && $company->legal_name !== '' && $company->legal_name !== $company->name)
                    {{ $company->legal_name }}<br>
                @endif
                @if (($company->address !== null && $company->address !== '') || ($company->city !== null && $company->city !== '') || ($company->postal_code !== null && $company->postal_code !== ''))
                    {{ trim((string) $company->address.' '.(string) $company->postal_code.' '.(string) $company->city) }}<br>
                @endif
                @if ($company->tax_identifier !== null && $company->tax_identifier !== '')
                    Identifiant fiscal : {{ $company->tax_identifier }}<br>
                @endif
                @if ($company->phone !== null && $company->phone !== '')
                    Tél : {{ $company->phone }}
                @endif
                @if ($company->email !== null && $company->email !== '')
                    @if ($company->phone !== null && $company->phone !== '')
                        —
                    @endif
                    {{ $company->email }}
                @endif
            </td>
            <td class="report-meta">
                <span class="report-title">{{ $title }}</span>
                <div class="report-period">{{ $periodLabel }}</div>
                <div class="report-generated">Généré le {{ now()->format('d/m/Y H:i') }}</div>
                @isset($fiscalYear)
                    <div class="report-generated">Exercice {{ $fiscalYear->code }}</div>
                @endisset
            </td>
        </tr>
    </table>

    @yield('content')

    <footer class="page-footer">
        {{ $company->name }} — {{ $title }}
    </footer>
</body>
</html>
