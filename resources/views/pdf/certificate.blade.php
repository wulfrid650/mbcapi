<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 0;
            font-size: 13px;
            background: #f8fafc;
        }
        .sheet {
            border: 6px solid #991b1b;
            background: #fffdf8;
            padding: 24px 28px;
            position: relative;
        }
        .sheet:before {
            content: "";
            position: absolute;
            inset: 10px;
            border: 2px solid #d4af37;
        }
        .content {
            position: relative;
            z-index: 1;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .company {
            font-size: 13px;
            letter-spacing: 0.2em;
            color: #991b1b;
            font-weight: bold;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            margin: 12px 0 8px;
            color: #7f1d1d;
        }
        .subtitle {
            font-size: 13px;
            color: #6b7280;
        }
        .recipient-label,
        .course-label {
            text-align: center;
            color: #6b7280;
            font-size: 13px;
            margin-top: 16px;
        }
        .recipient-name {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 8px 0 14px;
            color: #111827;
        }
        .course-name {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #991b1b;
            margin: 8px 0 12px;
        }
        .details {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 12px;
        }
        .details td {
            padding: 7px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .details td:first-child {
            width: 28%;
            font-weight: bold;
            color: #374151;
        }
        .verification-box {
            margin-top: 18px;
            padding: 12px 14px;
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            font-size: 12px;
        }
        .verification-layout {
            display: table;
            width: 100%;
        }
        .verification-text,
        .verification-qr {
            display: table-cell;
            vertical-align: middle;
        }
        .verification-text {
            width: 72%;
            padding-right: 12px;
        }
        .verification-qr {
            width: 28%;
            text-align: right;
        }
        .qr-image {
            width: 96px;
            height: 96px;
            padding: 5px;
            background: #ffffff;
            border: 1px solid #f59e0b;
            border-radius: 6px;
        }
        .qr-caption {
            margin-top: 4px;
            font-size: 11px;
            color: #92400e;
        }
        .footer {
            margin-top: 24px;
            display: table;
            width: 100%;
            font-size: 12px;
        }
        .footer .col {
            display: table-cell;
            width: 50%;
            vertical-align: bottom;
        }
        .signature {
            text-align: right;
        }
        .signature-line {
            margin-top: 38px;
            border-top: 1px solid #111827;
            display: inline-block;
            min-width: 220px;
            padding-top: 8px;
            text-align: center;
        }
        .meta {
            margin-top: 12px;
            font-size: 11px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="content">
            <div class="header">
                <div class="company">{{ $company['name'] }}</div>
                <div class="title">CERTIFICAT DE FORMATION</div>
                <div class="subtitle">Référence de vérification : {{ $reference }}</div>
            </div>

            <div class="recipient-label">Le présent certificat atteste que</div>
            <div class="recipient-name">{{ $participant_name }}</div>

            <div class="course-label">a suivi avec succès la formation</div>
            <div class="course-name">{{ $formation_title }}</div>

            <table class="details">
                <tr>
                    <td>Période</td>
                    <td>
                        du {{ $session_start_date?->format('d/m/Y') ?? 'N/A' }}
                        au {{ $session_end_date?->format('d/m/Y') ?? 'N/A' }}
                    </td>
                </tr>
                <tr>
                    <td>Durée</td>
                    <td>{{ $duration_label }}</td>
                </tr>
                <tr>
                    <td>Lieu</td>
                    <td>{{ $session_location ?: ($company['city'] ?: 'Douala') }}</td>
                </tr>
                <tr>
                    <td>Formateur</td>
                    <td>{{ $trainer_name ?: 'Équipe pédagogique MBC' }}</td>
                </tr>
                <tr>
                    <td>Date d'émission</td>
                    <td>{{ $issued_at->format('d/m/Y') }}</td>
                </tr>
            </table>

            <div class="verification-box">
                <div class="verification-layout">
                    <div class="verification-text">
                        Vérifiez l&apos;authenticité de ce certificat en scannant le QR code ci-dessous.
                    </div>
                    <div class="verification-qr">
                        <img src="{{ $qr_code_data_uri }}" alt="QR code de vérification" class="qr-image">
                        <div class="qr-caption">Scanner pour vérifier</div>
                    </div>
                </div>
            </div>

            <div class="footer">
                <div class="col">
                    <strong>{{ $company['short_name'] ?: 'MBC' }}</strong><br>
                    {{ $company['address'] }}<br>
                    {{ $company['phone'] }} | {{ $company['email'] }}
                </div>
                <div class="col signature">
                    <div>Fait à {{ $company['city'] ?: 'Douala' }}, le {{ $issued_at->format('d/m/Y') }}</div>
                    <div class="signature-line">
                        Direction {{ $company['short_name'] ?: 'MBC' }}
                    </div>
                </div>
            </div>

            <div class="meta">
                @if(!empty($company['rccm']) || !empty($company['niu']))
                    {{ trim(($company['rccm'] ? 'RCCM: ' . $company['rccm'] : '') . ($company['niu'] ? ' | NIU: ' . $company['niu'] : '')) }}
                @endif
            </div>
        </div>
    </div>
</body>
</html>
