<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Zoom; // Typo fix
use Illuminate\Validation\Rules\Password as PasswordRules;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\ResetPassword;

class NewPasswordController extends Controller
{
    /**
     * Handle an incoming password reset link request.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        if ($user) {
            $token = Str::random(60);

            // For simplicity and since we don't have a password_resets table set up with default auth unless verified,
            // we'll use a custom approach or standard DB table. 
            // Let's assume standard 'password_reset_tokens' table exists (Laravel default).
            // But to ensure custom Mailables, we manually handle it.

            \DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'email' => $request->email,
                    'token' => Hash::make($token),
                    'created_at' => now()
                ]
            );

            $resetUrl = config('app.frontend_url') . '/reinitialiser-mot-de-passe?token=' . $token . '&email=' . urlencode($request->email);

            try {
                Mail::to($user->email)->send(new ResetPassword($user, $resetUrl));
            } catch (\Exception $e) {
                return response()->json(['message' => 'Impossible d\'envoyer l\'email.'], 500);
            }
        }

        return response()->json(['status' => 'Nous vous avons envoyé par email le lien de réinitialisation du mot de passe !']);
    }

    /**
     * Handle an incoming new password request.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRules::min(6)],
        ]);

        $record = \DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json(['email' => 'Email ou token invalide.'], 422);
        }

        // Token expiration check (e.g., 60 minutes)
        if (\Carbon\Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            return response()->json(['email' => 'Ce lien de réinitialisation a expiré.'], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['email' => 'Utilisateur introuvable.'], 404);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['status' => 'Votre mot de passe a été réinitialisé !']);
    }
}
