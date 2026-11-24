@php
    use App\Support\PdfFormat;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $number }} — {{ $company->name }}</title>
    <style>
        @page { margin: 40px 36px 60px 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111111; margin: 0; }
        table { border-collapse: collapse; }
        .header { width: 100%; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .company-name { font-size: 14pt; font-weight: bold; }
        .muted { color: #4b5563; font-size: 8pt; line-height: 1.5; }
        .doc-title { font-size: 18pt; font-weight: bold; text-align: right; letter-spacing: 1px; }
        .doc-meta { text-align: right; font-size: 9pt; line-height: 1.6; }
        .draft { color: #b91c1c; font-weight: bold; text-align: right; font-size: 10pt; }
        .parties { width: 100%; margin-bottom: 16px; }
        .parties td { width: 50%; vertical-align: top; }
        .box { border: 1px solid #d1d5db; padding: 8px 10px; }
        .box-title { font-size: 7.5pt; text-transform: uppercase; color: #6b7280; letter-spacing: 1px; margin-bottom: 4px; }
        table.lines { width: 100%; margin-bottom: 14px; }
        table.lines th { background: #111111; color: #ffffff; font-size: 8pt; padding: 5px 6px; text-align: left; }
        table.lines td { border-bottom: 1px solid #e5e7eb; padding: 5px 6px; font-size: 8.5pt; }
        .num { text-align: right; white-space: nowrap; }
        table.totals { width: 45%; margin-left: 55%; }
        table.totals td { padding: 4px 6px; font-size: 9pt; }
        table.totals tr.grand td { border-top: 2px solid #111111; font-weight: bold; font-size: 11pt; }
        .words { margin-top: 14px; font-size: 9pt; }
        .notes { margin-top: 14px; font-size: 8.5pt; color: #374151; }
        .footer { position: fixed; bottom: -40px; left: 0; right: 0; text-align: center; font-size: 7pt; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="footer">
        {{ $company->legal_name ?: $company->name }}
        @if ($company->tax_identifier) — MF : {{ $company->tax_identifier }} @endif
        @if ($company->registration_number) — RNE : {{ $company->registration_number }} @endif
        @if ($company->address) — {{ $company->address }}{{ $company->city ? ', '.$company->city : '' }} @endif
    </div>

    <table class="header">
        <tr>
            <td>
                <div class="company-name">{{ $company->legal_name ?: $company->name }}</div>
                <div class="muted">
                    @if ($company->legal_form) {{ $company->legal_form }}<br> @endif
                    @if ($company->address) {{ $company->address }}<br> @endif
                    @if ($company->postal_code || $company->city) {{ trim(($company->postal_code ?? '').' '.($company->city ?? '')) }}<br> @endif
                    @if ($company->tax_identifier) Matricule fiscal : {{ $company->tax_identifier }}<br> @endif
                    @if ($company->phone) Tél. : {{ $company->phone }}<br> @endif
                    @if ($company->email) {{ $company->email }} @endif
                </div>
            </td>
            <td>
                <div class="doc-title">{{ $title }}</div>
                <div class="doc-meta">
                    N° <strong>{{ $isDraft ? 'BROUILLON' : $number }}</strong><br>
                    Date : {{ $date->format('d/m/Y') }}<br>
                    @foreach ($meta as $label => $value)
                        {{ $label }} : {{ $value }}<br>
                    @endforeach
                </div>
                @if ($isDraft)
                    <div class="draft">Document provisoire — sans valeur légale</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td></td>
            <td>
                <div class="box">
                    <div class="box-title">{{ $partyLabel }}</div>
                    <strong>{{ $party->legal_name ?: $party->name }}</strong><br>
                    @if ($party->address) {{ $party->address }}<br> @endif
                    @if ($party->postal_code || $party->city) {{ trim(($party->postal_code ?? '').' '.($party->city ?? '')) }}<br> @endif
                    @if ($party->tax_identifier) Matricule fiscal : {{ $party->tax_identifier }}<br> @endif
                    @if ($party->email) {{ $party->email }} @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="num">Qté</th>
                <th class="num">P.U. HT</th>
                <th class="num">Remise</th>
                <th class="num">TVA</th>
                <th class="num">Total HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="num">{{ PdfFormat::money((string) $line->quantity) }} {{ $line->unit }}</td>
                    <td class="num">{{ PdfFormat::money((string) $line->unit_price) }}</td>
                    <td class="num">{{ bccomp((string) $line->discount_percent, '0', 3) > 0 ? rtrim(rtrim((string) $line->discount_percent, '0'), '.').' %' : '—' }}</td>
                    <td class="num">{{ rtrim(rtrim((string) ($line->tax_rate ?? $line->taxRate?->rate ?? '0'), '0'), '.') }} %</td>
                    <td class="num">{{ PdfFormat::money((string) $line->line_subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @foreach ($totals as $label => $amount)
            <tr>
                <td>{{ $label }}</td>
                <td class="num">{{ PdfFormat::money($amount) }} {{ $currency }}</td>
            </tr>
        @endforeach
        <tr class="grand">
            <td>{{ $grandTotalLabel }}</td>
            <td class="num">{{ PdfFormat::money($grandTotal) }} {{ $currency }}</td>
        </tr>
    </table>

    <div class="words">
        {{ $wordsIntro }} <strong>{{ \App\Support\AmountInWords::dinars($grandTotal) }}</strong>.
    </div>

    @if (! empty($notes))
        <div class="notes"><strong>Notes :</strong> {!! nl2br(e($notes)) !!}</div>
    @endif
    @if (! empty($terms))
        <div class="notes"><strong>Conditions :</strong> {!! nl2br(e($terms)) !!}</div>
    @endif
</body>
</html>
