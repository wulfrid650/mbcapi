<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu - {{ $payment->reference }}</title>
    <style>
        @page {
            margin: 20mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #333;
            line-height: 1.6;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #c41e3a;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: #c41e3a;
            margin-bottom: 10px;
        }

        .company-info {
            font-size: 11px;
            color: #666;
        }

        .receipt-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 30px 0;
            text-transform: uppercase;
            color: #c41e3a;
        }

        .receipt-number {
            text-align: right;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .content {
            margin: 30px 0;
        }

        .row {
            margin: 15px 0;
            display: flex;
            justify-content: space-between;
        }

        .label {
            font-weight: bold;
            color: #666;
        }

        .value {
            color: #000;
        }

        .amount-box {
            background: #f5f5f5;
            padding: 20px;
            margin: 30px 0;
            border-left: 4px solid #c41e3a;
        }

        .amount-number {
            font-size: 24px;
            font-weight: bold;
            color: #c41e3a;
        }

        .amount-words {
            font-style: italic;
            color: #666;
            margin-top: 10px;
        }

        .footer {
            margin-top: 60px;
            border-top: 2px solid #ccc;
            padding-top: 20px;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }

        .signature-box {
            text-align: center;
            width: 45%;
        }

        .legal-notice {
            font-size: 10px;
            color: #999;
            text-align: center;
            margin-top: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">{{ $companyInfo['name'] }}</div>
        <div class="company-info">
            {{ $companyInfo['address'] }}<br>
            Tél: {{ $companyInfo['phone'] }} | Email: {{ $companyInfo['email'] }}<br>
            Web: {{ $companyInfo['website'] }}
        </div>
    </div>

    <div class="receipt-title">Reçu de Paiement</div>

    <div class="receipt-number">
        <strong>N° de Reçu:</strong> {{ $payment->reference }}<br>
        <strong>Date:</strong> {{ \Carbon\Carbon::parse($payment->paid_at)->format('d/m/Y') }}
    </div>

    <div class="content">
        <table class="info-table">
            <tr>
                <td class="label">Reçu de:</td>
                <td class="value">{{ $payment->payable->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Email:</td>
                <td class="value">{{ $payment->payable->email ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Pour:</td>
                <td class="value">{{ $payment->description }}</td>
            </tr>
            <tr>
                <td class="label">Mode de paiement:</td>
                <td class="value">
                    @if($payment->method === 'cash')
                        Espèces
                    @elseif($payment->method === 'bank_transfer')
                        Virement bancaire
                    @elseif($payment->method === 'check')
                        Chèque
                    @else
                        {{ ucfirst($payment->method) }}
                    @endif
                </td>
            </tr>
        </table>

        <div class="amount-box">
            <div class="amount-number">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</div>
            <div class="amount-words">Arrêté à la somme de: {{ $amountInWords }}</div>
        </div>
    </div>

    <div class="footer">
        <div class="signature-section">
            <div class="signature-box">
                <div>Le Client</div>
                <div style="height: 60px;"></div>
                <div style="border-top: 1px solid #999; padding-top: 5px;">Signature</div>
            </div>
            <div class="signature-box">
                <div>Pour MBC SARL</div>
                <div style="height: 60px;"></div>
                <div style="border-top: 1px solid #999; padding-top: 5px;">Cachet et Signature</div>
            </div>
        </div>

        <div class="legal-notice">
            Ce reçu est généré électroniquement et fait foi de paiement.<br>
            Merci de votre confiance.
        </div>
    </div>
</body>

</html>