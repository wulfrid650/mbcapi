<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $reportTitle ?? ('Rapport Financier - ' . $startDate->translatedFormat('F Y')) }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 14px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
            color: #333;
        }

        .header p {
            margin: 5px 0;
            color: #666;
        }

        .summary {
            margin-bottom: 30px;
        }

        .summary-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f4f4f4;
        }

        .amount {
            text-align: right;
            font-family: monospace;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ $reportTitle ?? 'Rapport Financier' }}</h1>
        <p>{{ $periodLabel ?? $startDate->translatedFormat('F Y') }}</p>
    </div>

    <div class="summary">
        <div class="summary-box">
            <h3>Résumé</h3>
            <p><strong>Période :</strong> {{ $startDate->format('d/m/Y') }} au {{ $endDate->format('d/m/Y') }}</p>
            <p><strong>Total Revenus :</strong> {{ number_format($totalRevenue, 2, ',', ' ') }} FCFA</p>
            <p><strong>Nombre de transactions :</strong> {{ $count }}</p>
        </div>
    </div>

    <h3>Détail par Méthode de Paiement</h3>
    <table>
        <thead>
            <tr>
                <th>Méthode</th>
                <th class="amount">Montant (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byMethod as $method => $amount)
                <tr>
                    <td>{{ ucfirst($method) }}</td>
                    <td class="amount">{{ number_format($amount, 2, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Document généré par MBC Digital le {{ now()->format('d/m/Y à H:i') }}</p>
    </div>
</body>

</html>
