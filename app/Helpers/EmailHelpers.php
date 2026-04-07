<?php

if (!function_exists('email_logo')) {
    /**
     * Get the email logo URL from settings
     * 
     * @return string|null
     */
    function email_logo()
    {
        $logo = \App\Models\SiteSetting::where('key', 'company_logo')->value('value');
        return $logo ? asset($logo) : null;
    }
}

if (!function_exists('email_company_name')) {
    /**
     * Get the company name from settings
     * 
     * @return string
     */
    function email_company_name()
    {
        return \App\Models\SiteSetting::where('key', 'company_name')->value('value') 
            ?? config('app.name', 'MBC SARL');
    }
}

if (!function_exists('email_site_name')) {
    /**
     * Get the site name from settings
     * 
     * @return string
     */
    function email_site_name()
    {
        return \App\Models\SiteSetting::where('key', 'company_name')->value('value') 
            ?? config('app.name', 'MBC SARL');
    }
}

if (!function_exists('email_tagline')) {
    /**
     * Get the site tagline from settings
     * 
     * @return string
     */
    function email_tagline()
    {
        return \App\Models\SiteSetting::where('key', 'company_slogan')->value('value') 
            ?? 'Excellence en Construction et Formation';
    }
}

if (!function_exists('email_contact_info')) {
    /**
     * Get contact information for emails
     * 
     * @return array
     */
    function email_contact_info()
    {
        return [
            'email' => \App\Models\SiteSetting::where('key', 'email')->value('value') ?? 'contact@madibabc.com',
            'phone' => \App\Models\SiteSetting::where('key', 'phone')->value('value') ?? '+237 692 65 35 90',
            'address' => \App\Models\SiteSetting::where('key', 'address')->value('value') ?? 'Douala, Cameroun',
        ];
    }
}
