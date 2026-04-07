<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Avancement des Projets</title>
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

        .status-badge {
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 10px;
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Avancement des Projets</h1>
        <p>Généré le {{ now()->format('d/m/Y H:i') }}</p>
        @if($status_filter)
            <p>Filtre statut : {{ $status_filter }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Projet</th>
                <th>Client</th>
                <th>Dates</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($projects as $project)
                <tr>
                    <td>
                        <strong>{{ $project->title }}</strong><br>
                        <small>{{ $project->location }} ({{ $project->year }})</small>
                    </td>
                    <td>{{ $project->client }}</td>
                    <td>
                        Du :
                        {{ $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('d/m/Y') : 'N/A' }}<br>
                        Au :
                        {{ $project->expected_end_date ? \Carbon\Carbon::parse($project->expected_end_date)->format('d/m/Y') : 'N/A' }}
                    </td>
                    <td>{{ $project->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>