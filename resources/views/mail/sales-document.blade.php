<!DOCTYPE html>
<html lang="fr">
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #111111;">
    <p>Bonjour {{ $customerName }},</p>
    <p>Veuillez trouver ci-joint votre {{ mb_strtolower($documentLabel) }} n° <strong>{{ $documentNumber }}</strong> d'un montant de <strong>{{ $totalLabel }}</strong>.</p>
    <p>Nous restons à votre disposition pour toute question.</p>
    <p>Cordialement,<br>{{ $companyName }}</p>
</body>
</html>
