<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Budgets des Projets</title>
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

        .amount {
            text-align: right;
            font-family: monospace;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Suivi des Budgets Projets</h1>
        <p>Généré le {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Projet</th>
                <th>Client</th>
                <th>Année</th>
                <th>Statut</th>
                <th class="amount">Budget Prévu</th>
            </tr>
        </thead>
        <tbody>
            @foreach($projects as $project)
                <tr>
                    <td>{{ $project->title }}</td>
                    <td>{{ $project->client }}</td>
                    <td>{{ $project->year }}</td>
                    <td>{{ $project->status }}</td>
                    <td class="amount">{{ $project->budget ? number_format($project->budget, 0, ',', ' ') : 'N/A' }} FCFA
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>