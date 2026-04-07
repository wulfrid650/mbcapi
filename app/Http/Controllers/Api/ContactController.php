<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Mail;
use App\Mail\QuoteRequest;
use App\Services\RecaptchaService;

/**
 * Contrôleur pour les demandes de contact (formulaire public)
 */
class ContactController extends Controller
{
    private function applyQuoteOnlyFilter($query): void
    {
        $query->where(function ($q) {
            $q->where('type', 'quote_request')
                ->orWhere('subject', 'like', '%devis%')
                ->orWhereNotNull('quote_number')
                ->orWhereNotNull('response_document')
                ->orWhereNotNull('response_message');
        });
    }

    /**
     * Soumettre une demande de contact (public)
     */
    public function submit(Request $request, RecaptchaService $recaptcha): JsonResponse
    {
        // Rate limiting: max 5 demandes par heure par IP
        $key = 'contact-request:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Trop de demandes. Réessayez dans {$seconds} secondes."
            ], 429);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'subject' => 'required|string|max:255',
            'service_type' => 'nullable|string|max:100',
            'message' => 'required|string|max:2000',
        ]);

        $recaptchaResult = $recaptcha->verifyToken($request->input('recaptcha_token'), 'contact');
        if (!$recaptchaResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $recaptchaResult['message'] ?? 'Échec de la vérification reCAPTCHA.',
            ], 422);
        }

        RateLimiter::hit($key, 3600); // 1 heure

        // Sanitization basique
        $validated['message'] = strip_tags($validated['message']);
        $validated['name'] = strip_tags($validated['name']);

        $contactRequest = ContactRequest::create($validated);

        // Send Quote Request / Contact Notification
        try {
            // Convert ContactRequest to object expected by Mailable or pass it directly
            // The Mailable expects $quote object with name, email, phone, project_type (service_type), description (message)
            $quoteObj = new \stdClass();
            $quoteObj->name = $validated['name'];
            $quoteObj->email = $validated['email'];
            $quoteObj->phone = $validated['phone'] ?? 'Non renseigné';
            $quoteObj->project_type = $validated['service_type'] ?? $validated['subject'];
            $quoteObj->description = $validated['message'];

            // Send to Contact (using specific address)
            Mail::to('contact@madibabc.com')->send(new QuoteRequest($quoteObj));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send quote request email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.',
            'data' => [
                'id' => $contactRequest->id
            ]
        ], 201);
    }

    /**
     * Liste des demandes de contact (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContactRequest::query();

        if ($request->boolean('quote_only')) {
            $this->applyQuoteOnlyFilter($query);
        }

        // Filtres
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('service_type') && $request->service_type) {
            $query->where('service_type', $request->service_type);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        $contacts = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $contacts->items(),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
                'unread_count' => ContactRequest::unread()->count(),
            ]
        ]);
    }

    /**
     * Voir une demande de contact (admin)
     */
    public function show(ContactRequest $contact): JsonResponse
    {
        // Marquer comme lu si nouveau
        if ($contact->status === 'new') {
            $contact->update(['status' => 'read']);
        }

        return response()->json([
            'success' => true,
            'data' => $contact->load('assignedUser')
        ]);
    }

    /**
     * Mettre à jour une demande de contact (admin)
     */
    public function update(Request $request, ContactRequest $contact): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:new,read,responded,archived',
            'admin_notes' => 'nullable|string|max:2000',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'responded' && $contact->status !== 'responded') {
            $validated['responded_at'] = now();
        }

        $contact->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Demande mise à jour',
            'data' => $contact->fresh()
        ]);
    }

    /**
     * Supprimer une demande de contact (admin)
     */
    public function destroy(ContactRequest $contact): JsonResponse
    {
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demande supprimée'
        ]);
    }

    /**
     * Répondre à une demande de devis (admin)
     */
    public function respondToQuote(Request $request, ContactRequest $contact): JsonResponse
    {
        $validated = $request->validate([
            'response_message' => 'required|string|max:2000',
            'response_document' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            'send_email' => 'nullable|boolean',
            'generate_quote_number' => 'nullable|boolean',
        ]);

        // Generate quote number if requested and doesn't exist
        if ($request->boolean('generate_quote_number') && !$contact->quote_number) {
            $validated['quote_number'] = ContactRequest::generateQuoteNumber();
        }

        // Handle file upload
        if ($request->hasFile('response_document')) {
            $file = $request->file('response_document');
            $filename = 'quote_' . $contact->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('quotes', $filename, 'public');
            $validated['response_document'] = $path;
        }

        // Update contact request with response
        $validated['status'] = 'responded';
        $validated['response_sent_at'] = now();
        $contact->update($validated);

        // Send email notification if requested
        if ($request->boolean('send_email', true)) {
            try {
                // Create email object
                $quoteResponse = new \stdClass();
                $quoteResponse->name = $contact->name;
                $quoteResponse->email = $contact->email;
                $quoteResponse->quote_number = $contact->quote_number;
                $quoteResponse->response_message = $validated['response_message'];
                $quoteResponse->has_document = isset($validated['response_document']);
                $quoteResponse->document_url = isset($validated['response_document']) 
                    ? asset('storage/' . $validated['response_document'])
                    : null;

                Mail::to($contact->email)->send(new \App\Mail\QuoteResponse($quoteResponse));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send quote response email: ' . $e->getMessage());
                // Continue anyway - response is saved
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Réponse envoyée avec succès',
            'data' => $contact->fresh()
        ]);
    }
}
