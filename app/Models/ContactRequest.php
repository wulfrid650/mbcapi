<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'subject',
        'service_type',
        'message',
        'type',
        'status',
        'admin_notes',
        'assigned_to',
        'responded_at',
        'metadata',
        'quote_number',
        'response_document',
        'response_message',
        'response_sent_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'response_sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = ['response_document_url'];

    public function getResponseDocumentUrlAttribute()
    {
        if ($this->response_document) {
            return url('storage/' . $this->response_document);
        }
        return null;
    }

    public static function generateQuoteNumber()
    {
        $year = date('Y');
        $lastQuote = self::whereYear('created_at', $year)
            ->whereNotNull('quote_number')
            ->orderBy('quote_number', 'desc')
            ->first();
        
        if ($lastQuote && $lastQuote->quote_number) {
            $lastNumber = intval(substr($lastQuote->quote_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return 'DEV-' . $year . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeUnread($query)
    {
        return $query->whereIn('status', ['new']);
    }
}
