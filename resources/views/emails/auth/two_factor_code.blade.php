@extends('emails.layouts.main')

@section('content')
    <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif; color: #374151; line-height: 1.6;">
        <p style="text-align: right; font-size: 14px; color: #6b7280; margin-bottom: 30px;">
            {{ $location }}, le {{ now()->format('d/m/Y') }}
        </p>

        <p style="font-size: 16px; margin-bottom: 25px;">Bonjour <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>,</p>

        <p style="font-size: 16px; margin-bottom: 20px;">Utilisez le code ci-dessous pour finaliser votre connexion :</p>

        <div style="text-align: center; margin: 35px 0;">
            <div style="display: inline-block; background: #f8fafc; border: 2px dashed #C1121F; border-radius: 12px; padding: 25px 45px; font-size: 42px; font-weight: 900; letter-spacing: 0.4em; color: #C1121F; font-family: 'Courier New', Courier, monospace;">
                {{ $code }}
            </div>
            <p style="margin-top: 15px; font-size: 14px; color: #ef4444; font-weight: 600;">
                Ce code expirera dans 10 minutes.
            </p>
        </div>

        <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 25px 0; border-radius: 4px;">
            <p style="margin: 0; font-size: 14px; font-weight: 600; color: #92400e;">
                ⚠️ Ne partagez jamais ce code avec qui que ce soit.
            </p>
        </div>

        <p style="font-size: 14px; color: #4b5563; margin-top: 30px;">
            Vous recevez cet email car une tentative de connexion a été effectuée sur votre compte.
        </p>

        <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <table cellpadding="0" cellspacing="0" width="100%" style="font-size: 13px; color: #6b7280;">
                <tr>
                    <td style="padding: 4px 0; width: 140px;">Date de connexion :</td>
                    <td style="padding: 4px 0; color: #374151;">{{ now()->format('d/m/Y H:i:s') }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;">Adresse IP :</td>
                    <td style="padding: 4px 0; color: #374151;">{{ $ip ?? 'Inconnue' }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;">Navigateur :</td>
                    <td style="padding: 4px 0; color: #374151;">
                        @if($browser)
                            @if(str_contains($browser, 'Firefox')) Firefox @elseif(str_contains($browser, 'Chrome')) Chrome @elseif(str_contains($browser, 'Safari')) Safari @elseif(str_contains($browser, 'Edg')) Edge @else Navigateur Web @endif
                        @else
                            Non reconnu
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;">Système utilisé :</td>
                    <td style="padding: 4px 0; color: #374151;">
                        @if($browser)
                            @if(str_contains($browser, 'Windows')) Windows @elseif(str_contains($browser, 'Macintosh')) macOS @elseif(str_contains($browser, 'Android')) Android @elseif(str_contains($browser, 'iPhone')) iOS @else Linux/Autre @endif
                        @else
                            Non reconnu
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>
@endsection
