<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Activité Utilisateurs</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
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
            padding: 6px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f4f4f4;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Journal d'Activité et Connexions</h1>
        <p>Généré le {{ now()->format('d/m/Y H:i') }}</p>
        @if($start_date || $end_date)
            <p>Période : {{ $start_date ?? '...' }} au {{ $end_date ?? '...' }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Date/Heure</th>
                <th>Utilisateur</th>
                <th>Action</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $log->user ? $log->user->name : 'Système/Inconnu' }}</td>
                    <td>
                        <strong>{{ $log->action }}</strong><br>
                        <span style="color: #666;">{{ Str::limit($log->description, 50) }}</span>
                    </td>
                    <td>{{ $log->ip_address }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>