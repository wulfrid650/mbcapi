<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Historique des Paiements</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            color: #333;
        }

        .header p {
            margin: 5px 0;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f4f4f4;
            font-weight: bold;
        }

        .right {
            text-align: right;
        }

        .status-completed {
            color: green;
        }

        .status-pending {
            color: orange;
        }

        .status-failed {
            color: red;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Historique des Paiements</h1>
        <p>Généré le {{ now()->format('d/m/Y H:i') }}</p>
        @if($start_date || $end_date)
            <p>Période : {{ $start_date ?? 'Début' }} au {{ $end_date ?? 'Maintenant' }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Référence</th>
                <th>Client</th>
                <th>Motif</th>
                <th>Méthode</th>
                <th>Statut</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment->created_at->format('d/m/Y') }}</td>
                    <td>{{ $payment->reference }}</td>
                    <td>{{ $payment->user->name ?? 'N/A' }}</td>
                    <td>{{ Str::limit($payment->description, 30) }}</td>
                    <td>{{ ucfirst($payment->method) }}</td>
                    <td>
                        <span class="status-{{ $payment->status }}">
                            {{ ucfirst($payment->status) }}
                        </span>
                    </td>
                    <td class="right">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>