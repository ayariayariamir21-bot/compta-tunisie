@php
    use App\Support\PdfFormat;
    $net = bcsub((string) $payment->amount, (string) $payment->withholding_amount, 3);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Certificat de retenue à la source {{ $payment->payment_number }}</title>
    <style>
        @page { margin: 45px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #111111; }
        h1 { font-size: 15pt; text-align: center; margin: 0 0 4px 0; letter-spacing: 1px; }
        .subtitle { text-align: center; color: #4b5563; font-size: 9pt; margin-bottom: 22px; }
        table { border-collapse: collapse; width: 100%; }
        .section { font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin: 16px 0 4px 0; }
        table.info td { border: 1px solid #d1d5db; padding: 6px 8px; }
        table.info td.label { width: 38%; background: #f3f4f6; }
        table.amounts th, table.amounts td { border: 1px solid #111111; padding: 7px 8px; }
        table.amounts th { background: #111111; color: #ffffff; font-size: 8.5pt; }
        .num { text-align: right; white-space: nowrap; }
        .signature { margin-top: 40px; width: 100%; }
        .signature td { width: 50%; vertical-align: top; font-size: 9pt; }
    </style>
</head>
<body>
    <h1>CERTIFICAT DE RETENUE D'IMPÔT</h1>
    <div class="subtitle">Retenue à la source — Règlement {{ $payment->payment_number }} du {{ $payment->payment_date->format('d/m/Y') }}</div>

    <div class="section">Payeur (organisme ayant opéré la retenue)</div>
    <table class="info">
        <tr><td class="label">Raison sociale</td><td>{{ $company->legal_name ?: $company->name }}</td></tr>
        <tr><td class="label">Matricule fiscal</td><td>{{ $company->tax_identifier ?: '—' }}</td></tr>
        <tr><td class="label">Adresse</td><td>{{ trim(($company->address ?? '').' '.($company->postal_code ?? '').' '.($company->city ?? '')) ?: '—' }}</td></tr>
    </table>

    <div class="section">Bénéficiaire</div>
    <table class="info">
        <tr><td class="label">Raison sociale / Nom</td><td>{{ $supplier->legal_name ?: $supplier->name }}</td></tr>
        <tr><td class="label">Matricule fiscal</td><td>{{ $supplier->tax_identifier ?: '—' }}</td></tr>
        <tr><td class="label">Adresse</td><td>{{ trim(($supplier->address ?? '').' '.($supplier->postal_code ?? '').' '.($supplier->city ?? '')) ?: '—' }}</td></tr>
    </table>

    <div class="section">Montants</div>
    <table class="amounts">
        <thead>
            <tr>
                <th>Montant brut payé</th>
                <th>Taux de retenue</th>
                <th>Montant de la retenue</th>
                <th>Montant net servi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="num">{{ PdfFormat::money((string) $payment->amount) }} {{ $payment->currency }}</td>
                <td class="num">{{ rtrim(rtrim((string) $payment->withholding_rate, '0'), '.') }} %</td>
                <td class="num"><strong>{{ PdfFormat::money((string) $payment->withholding_amount) }} {{ $payment->currency }}</strong></td>
                <td class="num">{{ PdfFormat::money($net) }} {{ $payment->currency }}</td>
            </tr>
        </tbody>
    </table>

    @if ($payment->allocations->isNotEmpty())
        <div class="section">Factures réglées</div>
        <table class="info">
            @foreach ($payment->allocations as $allocation)
                <tr>
                    <td class="label">{{ $allocation->purchaseInvoice?->supplier_invoice_number ?? $allocation->purchaseInvoice?->invoice_number ?? '—' }}</td>
                    <td class="num">{{ PdfFormat::money((string) $allocation->amount) }} {{ $payment->currency }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p style="margin-top: 18px;">
        Montant de la retenue en toutes lettres : <strong>{{ \App\Support\AmountInWords::dinars((string) $payment->withholding_amount) }}</strong>.
    </p>

    <table class="signature">
        <tr>
            <td>Fait le {{ now()->format('d/m/Y') }}</td>
            <td style="text-align: right;">Cachet et signature du payeur</td>
        </tr>
    </table>
</body>
</html>
