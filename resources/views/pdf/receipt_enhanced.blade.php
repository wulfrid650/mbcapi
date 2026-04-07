<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu {{ $receipt_number }}</title>
    <style>
        @page {
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #333;
            line-height: 1.3;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }

        .receipt-container {
            max-width: 100%;
            margin: 0 auto;
        }

        /* Header */
        .header {
            border-bottom: 2px solid #c41e3a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header-content {
            width: 100%;
        }

        .company-info {
            float: left;
            width: 60%;
        }

        .receipt-info {
            float: right;
            width: 35%;
            text-align: right;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #c41e3a;
            margin-bottom: 3px;
        }

        .company-details {
            font-size: 10px;
            color: #666;
            line-height: 1.6;
        }

        .receipt-title {
            background: #c41e3a;
            color: white;
            padding: 8px 15px;
            font-size: 14px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 10px;
        }

        .receipt-number {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .receipt-date {
            font-size: 11px;
            color: #666;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        /* Payer Section */
        .payer-section {
            background: #f8f9fa;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #c41e3a;
        }

        .payer-section h3 {
            margin: 0 0 10px;
            color: #c41e3a;
            font-size: 13px;
            text-transform: uppercase;
        }

        .payer-details {
            font-size: 12px;
        }

        .payer-name {
            font-weight: bold;
            font-size: 14px;
        }

        /* Payment Details Table */
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
        }

        .payment-table th {
            background: #c41e3a;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }

        .payment-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }

        .payment-table tr:last-child td {
            border-bottom: none;
        }

        .payment-table .label {
            color: #666;
            width: 40%;
        }

        .payment-table .value {
            font-weight: 500;
            text-align: right;
        }

        /* Amount Box */
        .amount-section {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #c41e3a;
            padding: 12px 15px;
            margin: 12px 0;
            border-radius: 6px;
        }

        .amount-row {
            margin: 8px 0;
        }

        .amount-label {
            color: #666;
            font-size: 11px;
        }

        .amount-value {
            font-size: 14px;
            float: right;
        }

        .amount-total {
            border-top: 1px solid #c41e3a;
            padding-top: 10px;
            margin-top: 10px;
        }

        .amount-total .amount-label {
            font-size: 13px;
            font-weight: bold;
            color: #c41e3a;
        }

        .amount-total .amount-value {
            font-size: 20px;
            font-weight: bold;
            color: #c41e3a;
        }

        .amount-words {
            font-style: italic;
            font-size: 10px;
            color: #666;
            margin-top: 8px;
            clear: both;
        }

        /* Discount Box */
        .discount-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
        }

        .discount-box .label {
            color: #155724;
            font-size: 11px;
        }

        .discount-box .value {
            color: #155724;
            font-weight: bold;
            float: right;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }

        .signature-section {
            margin: 30px 0;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            width: 180px;
            margin-top: 30px;
        }

        .signature-label {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }

        .legal-notice {
            font-size: 8px;
            color: #999;
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        /* QR Code placeholder */
        .qr-section {
            text-align: center;
            margin: 20px 0;
        }

        .qr-code {
            width: 80px;
            height: 80px;
            border: 1px solid #ccc;
            display: inline-block;
            background: #f5f5f5;
        }

        .qr-label {
            font-size: 9px;
            color: #666;
            margin-top: 5px;
        }

        /* Watermark */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(196, 30, 58, 0.05);
            font-weight: bold;
            z-index: -1;
        }
    </style>
</head>

<body>
    <div class="watermark">MBC</div>

    <div class="receipt-container">
        <!-- Header -->
        <div class="header clearfix">
            <div class="header-content">
                <div class="company-info">
                    <div class="company-name">{{ $company['name'] }}</div>
                    <div class="company-details">
                        {{ $company['address'] }}<br>
                        📞 {{ $company['phone'] }}<br>
                        ✉️ {{ $company['email'] }}<br>
                        🌐 {{ $company['website'] }}
                    </div>
                </div>
                <div class="receipt-info">
                    <div class="receipt-title">REÇU DE PAIEMENT</div>
                    <div class="receipt-number">N° {{ $receipt_number }}</div>
                    <div class="receipt-date">
                        Date : {{ $receipt_date->format('d/m/Y') }}<br>
                        Heure : {{ $receipt_date->format('H:i') }}
                    </div>
                    <div class="status-badge {{ $payment->status === 'completed' ? 'status-completed' : 'status-pending' }}">
                        {{ $payment->status === 'completed' ? '✓ PAYÉ' : '⏳ EN ATTENTE' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Payer Information -->
        <div class="payer-section">
            <h3>Informations du Payeur</h3>
            <div class="payer-details">
                <div class="payer-name">{{ $payer['name'] }}</div>
                @if($payer['company'])
                    <div>{{ $payer['company'] }}</div>
                @endif
                @if($payer['email'])
                    <div>Email : {{ $payer['email'] }}</div>
                @endif
                @if($payer['phone'])
                    <div>Tél : {{ $payer['phone'] }}</div>
                @endif
            </div>
        </div>

        <!-- Payment Details -->
        <table class="payment-table">
            <thead>
                <tr>
                    <th colspan="2">Détails du Paiement</th>
                </tr>
            </thead>
            <tbody>
                {{-- Removed redundant reference row --}}
                <tr>
                    <td class="label">Description</td>
                    <td class="value">{{ $description }}</td>
                </tr>
                <tr>
                    <td class="label">Motif</td>
                    <td class="value">{{ $purpose }}</td>
                </tr>
                <tr>
                    <td class="label">Mode de paiement</td>
                    <td class="value">{{ $method }}</td>
                </tr>
                @if($transaction_id)
                <tr>
                    <td class="label">ID Transaction</td>
                    <td class="value">{{ $transaction_id }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <!-- Discount if applicable -->
        @if($discount_amount && $discount_amount > 0)
        <div class="discount-box clearfix">
            <span class="label">
                🎁 Code promo appliqué : <strong>{{ $promo_code }}</strong>
            </span>
            <span class="value">- {{ number_format($discount_amount, 0, ',', ' ') }} {{ $currency }}</span>
        </div>
        @endif

        <!-- Amount Section -->
        <div class="amount-section">
            @if($original_amount && $original_amount != $amount)
            <div class="amount-row clearfix">
                <span class="amount-label">Montant original</span>
                <span class="amount-value">{{ number_format($original_amount, 0, ',', ' ') }} {{ $currency }}</span>
            </div>
            <div class="amount-row clearfix">
                <span class="amount-label">Réduction</span>
                <span class="amount-value">- {{ number_format($discount_amount, 0, ',', ' ') }} {{ $currency }}</span>
            </div>
            @endif
            <div class="amount-row amount-total clearfix">
                <span class="amount-label">MONTANT PAYÉ</span>
                <span class="amount-value">{{ number_format($amount, 0, ',', ' ') }} {{ $currency }}</span>
            </div>
            <div class="amount-words">
                Arrêté à la somme de : {{ $amount_in_words }}
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-label">Signature et cachet</div>
            </div>
        </div>

        <!-- Legal Notice -->
        <div class="legal-notice">
            Conservez-le comme preuve de paiement. Document généré automatiquement le {{ now()->format('d/m/Y à H:i') }}.
        </div>
    </div>
</body>

</html>
