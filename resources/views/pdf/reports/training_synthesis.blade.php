<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Synthèse des Formations</title>
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

        .summary-box {
            background: #f8f9fa;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
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

        .amount {
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Synthèse des Formations</h1>
        <p>Généré le {{ now()->format('d/m/Y H:i') }}</p>
        @if($year)
            <p>Année : {{ $year }}</p>
        @endif
    </div>

    <div class="summary-box">
        <h3>Résumé</h3>
        <p><strong>Total Inscriptions (Confirmées) :</strong> {{ $enrollments->count() }}</p>
        <p><strong>Revenu Total :</strong> {{ number_format($totalRevenue, 0, ',', ' ') }} FCFA</p>
    </div>

    <h3>Détail par Formation</h3>
    <table>
        <thead>
            <tr>
                <th>Formation</th>
                <th>Nombre d'inscrits</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byFormation as $formation => $cnt)
                <tr>
                    <td>{{ $formation }}</td>
                    <td>{{ $cnt }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Liste des Inscriptions</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Apprenant</th>
                <th>Formation</th>
                <th class="amount">Payé</th>
            </tr>
        </thead>
        <tbody>
            @foreach($enrollments as $enrollment)
                <tr>
                    <td>{{ $enrollment->created_at->format('d/m/Y') }}</td>
                    <td>{{ $enrollment->full_name }}</td>
                    <td>{{ $enrollment->formation ? $enrollment->formation->title : 'N/A' }}</td>
                    <td class="amount">{{ number_format($enrollment->amount_paid, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>