<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ config('app.name', 'MBC SARL') }}</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {font-family: Arial, Helvetica, sans-serif !important;}
    </style>
    <![endif]-->
    <style>
        /* Reset styles */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            background-color: #f3f4f6;
        }
        
        img {
            border: 0;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
            max-width: 100%;
            height: auto;
        }
        
        table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        
        /* Main container */
        .email-wrapper {
            width: 100%;
            background-color: #f3f4f6;
            padding: 20px 0;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        /* Header */
        .email-header {
            background: linear-gradient(135deg, #C1121F 0%, #8b0d17 100%);
            padding: 30px 20px;
            text-align: center;
        }
        
        .email-header-logo {
            max-width: 180px;
            height: auto;
            margin-bottom: 15px;
        }
        
        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .email-header p {
            color: #ffffff;
            margin: 10px 0 0;
            font-size: 14px;
            opacity: 0.95;
        }
        
        /* Content */
        .email-content {
            padding: 40px 30px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #1f2937;
        }
        
        .email-content h2 {
            color: #C1121F;
            font-size: 22px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .email-content h3 {
            color: #374151;
            font-size: 18px;
            font-weight: 600;
            margin-top: 25px;
            margin-bottom: 15px;
        }
        
        .email-content p {
            margin: 0 0 16px;
        }
        
        /* Button */
        .btn {
            display: inline-block;
            background-color: #C1121F;
            color: #ffffff !important;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            text-align: center;
            transition: background-color 0.3s ease;
        }
        
        .btn:hover {
            background-color: #990f19;
        }
        
        .btn-secondary {
            background-color: #0B0B0B;
        }
        
        .btn-secondary:hover {
            background-color: #374151;
        }
        
        /* Info boxes */
        .info-box {
            background-color: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .success-box {
            background-color: #f0fdf4;
            border-left: 4px solid #22c55e;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .warning-box {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .highlight-box {
            background-color: #fff1f2;
            border-left: 4px solid #C1121F;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        /* Divider */
        .divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 30px 0;
        }
        
        /* Footer */
        .email-footer {
            background-color: #f9fafb;
            padding: 30px 20px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .email-footer p {
            margin: 8px 0;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5;
        }
        
        .email-footer a {
            color: #C1121F;
            text-decoration: none;
            font-weight: 500;
        }
        
        .email-footer a:hover {
            text-decoration: underline;
        }
        
        .social-links {
            margin: 20px 0 10px;
        }
        
        .social-links a {
            display: inline-block;
            margin: 0 8px;
            color: #6b7280;
            text-decoration: none;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                border-radius: 0 !important;
            }
            
            .email-content {
                padding: 30px 20px !important;
            }
            
            .email-header {
                padding: 25px 15px !important;
            }
            
            .email-header h1 {
                font-size: 24px !important;
            }
            
            .btn {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box;
            }
        }
    </style>
</head>

<body style="margin: 0; padding: 0; background-color: #f3f4f6;">
    <table role="presentation" class="email-wrapper" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" class="email-container" cellpadding="0" cellspacing="0" width="600">
                    <!-- Header -->
                    <tr>
                        <td class="email-header" style="background: linear-gradient(135deg, #C1121F 0%, #8b0d17 100%); padding: 30px 20px; text-align: center;">
                            @php
                                $logo = \App\Models\SiteSetting::where('key', 'company_logo')->value('value');
                                $siteName = \App\Models\SiteSetting::where('key', 'company_name')->value('value') ?? config('app.name', 'MBC SARL');
                            @endphp
                            
                            @if($logo)
                                <img src="{{ asset('storage/' . $logo) }}" alt="{{ $siteName }}" class="email-header-logo" style="max-width: 180px; height: auto; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto;">
                            @else
                                <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 800; letter-spacing: 0.05em;">{{ $siteName }}</h1>
                            @endif
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="email-content" style="padding: 40px 30px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #1f2937;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="email-footer" style="background-color: #f9fafb; padding: 30px 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                            @php
                                $companyName = \App\Models\SiteSetting::where('key', 'company_name')->value('value') ?? 'Madiba Building Construction SARL';
                                $companyEmail = \App\Models\SiteSetting::where('key', 'email')->value('value') ?? 'contact@madibabc.com';
                                $companyPhone = \App\Models\SiteSetting::where('key', 'phone')->value('value') ?? '+237 692 65 35 90';
                                $companyAddress = \App\Models\SiteSetting::where('key', 'address')->value('value') ?? 'Douala, Cameroun';
                                $tagline = \App\Models\SiteSetting::where('key', 'company_slogan')->value('value') ?? "Ensemble, bâtissons l'Afrique";
                            @endphp
                            
                            <p style="margin: 8px 0; font-size: 14px; color: #374151; font-weight: 600;">{{ $companyName }}</p>
                            <p style="margin: 8px 0; font-size: 13px; color: #6b7280;">{{ $tagline }}</p>
                            
                            <div class="divider" style="height: 1px; background-color: #e5e7eb; margin: 20px 40px;"></div>
                            
                            <p style="margin: 8px 0; font-size: 13px; color: #6b7280;">
                                📧 <a href="mailto:{{ $companyEmail }}" style="color: #C1121F; text-decoration: none;">{{ $companyEmail }}</a><br>
                                📱 {{ $companyPhone }}<br>
                                📍 {{ $companyAddress }}
                            </p>
                            
                            <p style="margin: 20px 0 8px; font-size: 13px; color: #6b7280;">
                                <a href="{{ config('app.url') }}" style="color: #C1121F; text-decoration: none; font-weight: 500;">Visiter notre site web</a>
                            </p>
                            
                            <p style="margin: 20px 0 8px; font-size: 12px; color: #9ca3af;">
                                &copy; {{ date('Y') }} {{ $companyName }}. Tous droits réservés.
                            </p>
                            
                            <p style="margin: 8px 0; font-size: 11px; color: #9ca3af;">
                                Vous recevez cet email car vous avez un compte sur notre plateforme.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>