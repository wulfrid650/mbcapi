<?php

namespace App\Services;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Service pour la génération des reçus de paiement
 */
class ReceiptService
{
    /**
     * Générer un numéro de reçu unique
     */
    public function generateReceiptNumber(): string
    {
        $unique = false;
        $number = '';

        while (!$unique) {
            $number = 'REC-' . Str::upper(Str::random(8));
            $exists = Payment::where('receipt_number', $number)->exists();
            if (!$exists) {
                $unique = true;
            }
        }

        return $number;
    }

    /**
     * Générer un reçu PDF pour un paiement
     */
    public function generateReceipt(Payment $payment, bool $forceRegenerate = false): array
    {
        // Utiliser une transaction pour sécuriser la génération du numéro de reçu
        return DB::transaction(function () use ($payment, $forceRegenerate) {
            // Recharger le paiement pour être sûr d'avoir les données à jour (avec un lock)
            $payment = Payment::lockForUpdate()->find($payment->id);

            // Si le reçu existe déjà et qu'on ne force pas la régénération
            if ($payment->receipt_path && Storage::disk('public')->exists($payment->receipt_path) && !$forceRegenerate) {
                return [
                    'success' => true,
                    'path' => $payment->receipt_path,
                    'url' => Storage::disk('public')->url($payment->receipt_path),
                    'receipt_number' => $payment->receipt_number,
                ];
            }

            // Générer le numéro de reçu si pas encore fait
            if (!$payment->receipt_number) {
                $payment->receipt_number = $this->generateReceiptNumber();
            }

            // Charger les relations
            $payment->load(['user', 'payable', 'promoCode', 'validatedByUser']);

            // Préparer les données pour le PDF
            $data = $this->prepareReceiptData($payment);

            // Générer le PDF
            $pdf = Pdf::loadView('pdf.receipt_enhanced', $data);
            $pdf->setPaper('a4');

            // Sauvegarder le fichier (format: receipts/2026/04/REC-202604-00001.pdf)
            $filename = sprintf('receipts/%s/%s.pdf', date('Y/m'), $payment->receipt_number);
            Storage::disk('public')->put($filename, $pdf->output());

            // Mettre à jour le paiement
            $payment->update([
                'receipt_number' => $payment->receipt_number,
                'receipt_generated_at' => now(),
                'receipt_path' => $filename,
            ]);

            return [
                'success' => true,
                'path' => $filename,
                'url' => Storage::disk('public')->url($filename),
                'receipt_number' => $payment->receipt_number,
            ];
        });
    }

    /**
     * Préparer les données pour le template de reçu
     */
    private function prepareReceiptData(Payment $payment): array
    {
        $payer = $payment->user;

        // Déterminer le nom du payeur
        $payerName = $payment->payer_name ?? $payer?->name ?? 'Client';
        $payerEmail = $payment->payer_email ?? $payer?->email ?? '';
        $payerPhone = $payment->payer_phone ?? $payer?->phone ?? '';

        // Déterminer la description du motif
        $purposeDescription = $this->getPurposeDescription($payment);

        // Montant en lettres
        $amountInWords = $this->numberToWords((int) $payment->amount) . ' Francs CFA';

        return [
            'payment' => $payment,
            'receipt_number' => $payment->receipt_number,
            'receipt_date' => $payment->validated_at ?? $payment->paid_at ?? $payment->created_at,
            'payer' => [
                'name' => $payerName,
                'email' => $payerEmail,
                'phone' => $payerPhone,
                'company' => $payer?->company_name ?? null,
            ],
            'amount' => $payment->amount,
            'original_amount' => $payment->original_amount,
            'discount_amount' => $payment->discount_amount,
            'promo_code' => $payment->promoCode?->code,
            'currency' => $payment->currency,
            'method' => $payment->method_label,
            'description' => $payment->description,
            'purpose' => $purposeDescription,
            'amount_in_words' => $amountInWords,
            'transaction_id' => $payment->transaction_id,
            'company' => [
                'name' => \App\Models\SiteSetting::get('company_name', config('app.company_name', 'MADIBA Business Center')),
                'address' => \App\Models\SiteSetting::get('address_full', \App\Models\SiteSetting::get('address_city', config('app.company_address', 'Yaoundé, Cameroun'))),
                'phone' => \App\Models\SiteSetting::get('phone', config('app.company_phone', '+237 6XX XXX XXX')),
                'email' => \App\Models\SiteSetting::get('email', config('app.company_email', 'contact@madibabc.com')),
                'website' => \App\Models\SiteSetting::get('website_url', config('app.url', 'https://madibabc.com')),
            ],
        ];
    }

    /**
     * Obtenir la description du motif de paiement
     */
    private function getPurposeDescription(Payment $payment): string
    {
        $purposes = [
            'formation_payment' => 'Paiement inscription formation',
            'formation_installment' => 'Paiement tranche formation',
            'service_payment' => 'Paiement service',
            'project_payment' => 'Paiement projet/chantier',
            'project_advance' => 'Avance projet/chantier',
            'project_installment' => 'Tranche paiement projet',
            'consultation' => 'Frais de consultation',
            'other' => 'Autre paiement',
        ];

        $base = $purposes[$payment->purpose] ?? 'Paiement';

        if ($payment->purpose_detail) {
            $base .= ' - ' . $payment->purpose_detail;
        }

        return $base;
    }

    /**
     * Convertir un nombre en lettres (français)
     */
    public function numberToWords(int $number): string
    {
        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = ['', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

        if ($number == 0) {
            return 'zéro';
        }

        if ($number < 0) {
            return 'moins ' . $this->numberToWords(abs($number));
        }

        $words = '';

        if (($millions = (int) ($number / 1000000)) > 0) {
            $words .= ($millions == 1 ? 'un million ' : $this->numberToWords($millions) . ' millions ');
            $number %= 1000000;
        }

        if (($thousands = (int) ($number / 1000)) > 0) {
            $words .= ($thousands == 1 ? 'mille ' : $this->numberToWords($thousands) . ' mille ');
            $number %= 1000;
        }

        if (($hundreds = (int) ($number / 100)) > 0) {
            $words .= ($hundreds == 1 ? 'cent ' : $this->numberToWords($hundreds) . ' cent ');
            $number %= 100;
        }

        if ($number > 0) {
            if ($number < 20) {
                $words .= $units[$number];
            } else {
                $tenIndex = (int) ($number / 10);
                $unit = $number % 10;

                // Cas spéciaux pour 70, 80, 90
                if ($tenIndex == 7 || $tenIndex == 9) {
                    $words .= $tens[$tenIndex];
                    if ($unit == 1 && $tenIndex == 7) {
                        $words .= ' et ';
                    } else {
                        $words .= '-';
                    }
                    $words .= $units[10 + $unit];
                } elseif ($tenIndex == 8) {
                    $words .= $tens[$tenIndex];
                    if ($unit > 0) {
                        $words .= '-' . $units[$unit];
                    } else {
                        $words .= 's'; // quatre-vingts
                    }
                } else {
                    $words .= $tens[$tenIndex];
                    if ($unit == 1) {
                        $words .= ' et un';
                    } elseif ($unit > 0) {
                        $words .= '-' . $units[$unit];
                    }
                }
            }
        }

        return trim($words);
    }

    /**
     * Télécharger ou streamer un reçu
     */
    public function downloadReceipt(Payment $payment)
    {
        $result = $this->generateReceipt($payment);

        if (!$result['success']) {
            throw new \Exception('Impossible de générer le reçu');
        }

        $path = Storage::disk('public')->path($result['path']);

        return response()->download($path, "Recu-{$payment->receipt_number}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Envoyer le reçu par email
     */
    public function sendReceiptByEmail(Payment $payment, ?string $email = null): bool
    {
        $result = $this->generateReceipt($payment);

        if (!$result['success']) {
            return false;
        }

        $recipientEmail = $email ?? $payment->payer_email ?? $payment->user?->email;

        if (!$recipientEmail) {
            return false;
        }

        try {
            \Mail::to($recipientEmail)->send(new \App\Mail\PaymentReceiptWithAttachment($payment, $result['path']));
            return true;
        } catch (\Exception $e) {
            \Log::error('Erreur envoi reçu email: ' . $e->getMessage());
            return false;
        }
    }
}
