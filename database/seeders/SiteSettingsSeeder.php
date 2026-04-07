<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SiteSetting;

/**
 * Seeder pour les paramètres du site
 * Ces paramètres sont modifiables par l'admin
 */
class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ==========================================
            // Informations générales
            // ==========================================
            [
                'key' => 'company_name',
                'value' => 'MADIBA BUILDING CONSTRUCTION SARL',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Nom de l\'entreprise',
                'description' => 'Nom officiel de l\'entreprise',
                'is_public' => true,
            ],
            [
                'key' => 'company_short_name',
                'value' => 'MBC SARL',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Nom court',
                'description' => 'Acronyme de l\'entreprise',
                'is_public' => true,
            ],
            [
                'key' => 'company_slogan',
                'value' => 'Ensemble bâtissons l\'Afrique',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Slogan',
                'description' => 'Slogan de l\'entreprise',
                'is_public' => true,
            ],
            [
                'key' => 'site_tagline',
                'value' => 'De la conception architecturale à la réalisation d\'ouvrages complexes, nous maîtrisons chaque étape',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Tagline du site',
                'description' => 'Phrase d\'accroche pour le site web',
                'is_public' => true,
            ],
            [
                'key' => 'company_description',
                'value' => 'MBC SARL est une Société à Responsabilité Limitée spécialisée dans les travaux de bâtiments et génie civil, engagée dans le développement durable des infrastructures camerounaises. Fondée en 2023, MADIBA BUILDING CONSTRUCTION est née de la vision d\'un groupe d\'ingénieurs déterminés à contribuer au développement des infrastructures avec des standards internationaux.',
                'type' => 'textarea',
                'group' => 'general',
                'label' => 'Description de l\'entreprise',
                'description' => 'Description utilisée sur la page À propos',
                'is_public' => true,
            ],
            [
                'key' => 'company_year_founded',
                'value' => '2025',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Année de fondation',
                'description' => 'Année de création de l\'entreprise',
                'is_public' => true,
            ],
            [
                'key' => 'company_legal_form',
                'value' => 'Société à Responsabilité Limitée (SARL)',
                'type' => 'text',
                'group' => 'legal',
                'label' => 'Forme juridique',
                'description' => 'Forme juridique de l\'entreprise',
                'is_public' => true,
            ],
            [
                'key' => 'company_rccm',
                'value' => 'CM-DLA-01-2025-B-01235',
                'type' => 'text',
                'group' => 'legal',
                'label' => 'Registre de Commerce (RCCM)',
                'description' => 'Numéro du registre de commerce',
                'is_public' => true,
            ],
            [
                'key' => 'company_niu',
                'value' => 'M092512806Z3W',
                'type' => 'text',
                'group' => 'legal',
                'label' => 'N° Contribuable (NIU)',
                'description' => 'Numéro d\'identification unique du contribuable',
                'is_public' => true,
            ],
            [
                'key' => 'company_tax_regime',
                'value' => 'IGS',
                'type' => 'text',
                'group' => 'legal',
                'label' => 'Régime d\'imposition',
                'description' => 'Régime fiscal de l\'entreprise',
                'is_public' => true,
            ],
            [
                'key' => 'company_activity',
                'value' => 'Travaux de bâtiments et Génie civil',
                'type' => 'text',
                'group' => 'legal',
                'label' => 'Activité principale',
                'description' => 'Activité principale de l\'entreprise',
                'is_public' => true,
            ],
            [
                'key' => 'company_headquarters',
                'value' => 'Douala, LOGBESSOU',
                'type' => 'text',
                'group' => 'legal',
                'label' => 'Siège social',
                'description' => 'Adresse du siège social',
                'is_public' => true,
            ],
            [
                'key' => 'company_logo',
                'value' => null,
                'type' => 'image',
                'group' => 'branding',
                'label' => 'Logo principal',
                'description' => 'Logo de l\'entreprise (PNG/SVG recommandé, max 2MB)',
                'is_public' => true,
            ],
            [
                'key' => 'company_logo_white',
                'value' => null,
                'type' => 'image',
                'group' => 'branding',
                'label' => 'Logo blanc',
                'description' => 'Logo pour fond sombre (PNG/SVG recommandé)',
                'is_public' => true,
            ],
            [
                'key' => 'company_icon',
                'value' => null,
                'type' => 'image',
                'group' => 'branding',
                'label' => 'Icône',
                'description' => 'Icône carrée de l\'entreprise (PNG, 512x512px recommandé)',
                'is_public' => true,
            ],
            [
                'key' => 'company_favicon',
                'value' => null,
                'type' => 'image',
                'group' => 'branding',
                'label' => 'Favicon',
                'description' => 'Icône du site (ICO ou PNG, 32x32px)',
                'is_public' => true,
            ],
            [
                'key' => 'company_og_image',
                'value' => null,
                'type' => 'image',
                'group' => 'branding',
                'label' => 'Image Open Graph',
                'description' => 'Image pour partage réseaux sociaux (1200x630px)',
                'is_public' => true,
            ],

            // ==========================================
            // Informations de contact
            // ==========================================
            [
                'key' => 'phone',
                'value' => '+237 692 65 35 90',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Téléphone principal',
                'description' => 'Numéro de téléphone principal',
                'is_public' => true,
            ],
            [
                'key' => 'phone_secondary',
                'value' => '+237 676 94 91 03',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Téléphone secondaire',
                'description' => 'Numéro de téléphone alternatif',
                'is_public' => true,
            ],
            [
                'key' => 'email',
                'value' => 'contact@madibabc.com',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Email',
                'description' => 'Email de contact principal',
                'is_public' => true,
            ],
            [
                'key' => 'email_commercial',
                'value' => 'commercial@madibabc.com',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Email commercial',
                'description' => 'Email pour les demandes commerciales',
                'is_public' => true,
            ],
            [
                'key' => 'address',
                'value' => 'Douala – Cameroun',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Adresse courte',
                'description' => 'Adresse simplifiée (ville)',
                'is_public' => true,
            ],
            [
                'key' => 'address_full',
                'value' => 'Entrée principale IUC Logbesou, Douala, Cameroun',
                'type' => 'textarea',
                'group' => 'contact',
                'label' => 'Adresse complète',
                'description' => 'Adresse complète de l\'entreprise',
                'is_public' => true,
            ],
            [
                'key' => 'working_hours',
                'value' => 'Lundi - Vendredi: 8h00 - 18h00 | Samedi: 8h00 - 13h00',
                'type' => 'text',
                'group' => 'contact',
                'label' => 'Horaires d\'ouverture',
                'description' => 'Horaires de travail',
                'is_public' => true,
            ],
            [
                'key' => 'map_embed',
                'value' => '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d127371.47259073984!2d9.6683744!3d4.0510564!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1061128be2e1fe6d%3A0x92daa1444781c48b!2sDouala%2C%20Cameroun!5e0!3m2!1sfr!2sfr!4v1234567890" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>',
                'type' => 'textarea',
                'group' => 'contact',
                'label' => 'Carte Google Maps',
                'description' => 'Code embed de la carte Google Maps',
                'is_public' => true,
            ],

            // ==========================================
            // Réseaux sociaux
            // ==========================================
            [
                'key' => 'facebook_url',
                'value' => 'https://facebook.com/madibabtp',
                'type' => 'text',
                'group' => 'social',
                'label' => 'Facebook',
                'description' => 'URL de la page Facebook',
                'is_public' => true,
            ],
            [
                'key' => 'instagram_url',
                'value' => 'https://instagram.com/madibabtp',
                'type' => 'text',
                'group' => 'social',
                'label' => 'Instagram',
                'description' => 'URL du compte Instagram',
                'is_public' => true,
            ],
            [
                'key' => 'linkedin_url',
                'value' => 'https://linkedin.com/company/madibabtp',
                'type' => 'text',
                'group' => 'social',
                'label' => 'LinkedIn',
                'description' => 'URL de la page LinkedIn',
                'is_public' => true,
            ],
            [
                'key' => 'twitter_url',
                'value' => null,
                'type' => 'text',
                'group' => 'social',
                'label' => 'Twitter/X',
                'description' => 'URL du compte Twitter/X',
                'is_public' => true,
            ],
            [
                'key' => 'youtube_url',
                'value' => 'https://youtube.com/@madibabtp',
                'type' => 'text',
                'group' => 'social',
                'label' => 'YouTube',
                'description' => 'URL de la chaîne YouTube',
                'is_public' => true,
            ],
            [
                'key' => 'whatsapp_number',
                'value' => '+237692653590',
                'type' => 'text',
                'group' => 'social',
                'label' => 'WhatsApp',
                'description' => 'Numéro WhatsApp (format international sans espaces)',
                'is_public' => true,
            ],

            // ==========================================
            // Hero Section (page d'accueil)
            // ==========================================
            [
                'key' => 'hero_title',
                'value' => 'Construire avec rigueur et vision durable',
                'type' => 'text',
                'group' => 'hero',
                'label' => 'Titre Hero',
                'description' => 'Titre principal de la section hero',
                'is_public' => true,
            ],
            [
                'key' => 'hero_subtitle',
                'value' => 'De la conception architecturale à la réalisation d\'ouvrages complexes, nous maîtrisons chaque étape. Une équipe jeune, qualifiée et expérimentée à votre service.',
                'type' => 'textarea',
                'group' => 'hero',
                'label' => 'Sous-titre Hero',
                'description' => 'Description sous le titre hero',
                'is_public' => true,
            ],
            [
                'key' => 'hero_image',
                'value' => '/images/hero-construction.jpg',
                'type' => 'image',
                'group' => 'hero',
                'label' => 'Image Hero',
                'description' => 'Image de fond de la section hero',
                'is_public' => true,
            ],
            [
                'key' => 'hero_cta_text',
                'value' => 'Demander un devis gratuit',
                'type' => 'text',
                'group' => 'hero',
                'label' => 'Texte du bouton CTA',
                'description' => 'Texte du bouton d\'appel à l\'action',
                'is_public' => true,
            ],

            // ==========================================
            // Statistiques
            // ==========================================
            [
                'key' => 'stats_projects',
                'value' => '15',
                'type' => 'text',
                'group' => 'stats',
                'label' => 'Projets réalisés',
                'description' => 'Nombre de projets réalisés',
                'is_public' => true,
            ],
            [
                'key' => 'stats_years',
                'value' => '3',
                'type' => 'text',
                'group' => 'stats',
                'label' => 'Années d\'expérience',
                'description' => 'Années d\'expérience',
                'is_public' => true,
            ],
            [
                'key' => 'stats_employees',
                'value' => '50',
                'type' => 'text',
                'group' => 'stats',
                'label' => 'Employés',
                'description' => 'Nombre d\'employés',
                'is_public' => true,
            ],
            [
                'key' => 'stats_regions',
                'value' => '3',
                'type' => 'text',
                'group' => 'stats',
                'label' => 'Régions couvertes',
                'description' => 'Nombre de régions couvertes',
                'is_public' => true,
            ],

            // ==========================================
            // Maintenance
            // ==========================================
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'maintenance',
                'label' => 'Mode maintenance',
                'description' => 'Activer le mode maintenance du site',
                'is_public' => true,
            ],
            [
                'key' => 'maintenance_message',
                'value' => 'Le site est actuellement en maintenance. Nous serons de retour très bientôt !',
                'type' => 'textarea',
                'group' => 'maintenance',
                'label' => 'Message de maintenance',
                'description' => 'Message affiché pendant la maintenance',
                'is_public' => true,
            ],

            // ==========================================
            // SEO
            // ==========================================
            [
                'key' => 'seo_title',
                'value' => 'MBC - Construction & Génie Civil | Votre partenaire de confiance au Cameroun',
                'type' => 'text',
                'group' => 'seo',
                'label' => 'Titre SEO',
                'description' => 'Titre par défaut pour le référencement',
                'is_public' => true,
            ],
            [
                'key' => 'seo_description',
                'value' => 'MBC (Madiba Building Construction) est une entreprise de construction et génie civil engagée dans le développement durable des infrastructures camerounaises. Architecture, BTP, Formation.',
                'type' => 'textarea',
                'group' => 'seo',
                'label' => 'Description SEO',
                'description' => 'Meta description par défaut',
                'is_public' => true,
            ],
            [
                'key' => 'seo_keywords',
                'value' => 'construction cameroun, btp douala, rénovation yaoundé, formation maçonnerie, entreprise construction cameroun',
                'type' => 'text',
                'group' => 'seo',
                'label' => 'Mots-clés SEO',
                'description' => 'Mots-clés pour le référencement',
                'is_public' => true,
            ],

            // ==========================================
            // Analytics (GA4, etc.)
            // ==========================================
            [
                'key' => 'ga4_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'analytics',
                'label' => 'Google Analytics activé',
                'description' => 'Activer/désactiver le suivi Google Analytics',
                'is_public' => true,
            ],
            [
                'key' => 'ga4_id',
                'value' => 'G-T7060CCYZQ',
                'type' => 'text',
                'group' => 'analytics',
                'label' => 'ID Google Analytics (GA4)',
                'description' => 'ID de mesure GA4 (ex: G-XXXXXXXXXX)',
                'is_public' => true,
            ],
            [
                'key' => 'gtm_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'analytics',
                'label' => 'Google Tag Manager activé',
                'description' => 'Activer/désactiver Google Tag Manager',
                'is_public' => true,
            ],
            [
                'key' => 'gtm_id',
                'value' => '',
                'type' => 'text',
                'group' => 'analytics',
                'label' => 'ID Google Tag Manager',
                'description' => 'ID du conteneur GTM (ex: GTM-XXXXXXX)',
                'is_public' => true,
            ],
            [
                'key' => 'fb_pixel_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'analytics',
                'label' => 'Facebook Pixel activé',
                'description' => 'Activer/désactiver le pixel Facebook',
                'is_public' => true,
            ],
            [
                'key' => 'fb_pixel_id',
                'value' => '',
                'type' => 'text',
                'group' => 'analytics',
                'label' => 'ID Facebook Pixel',
                'description' => 'ID du pixel Facebook',
                'is_public' => true,
            ],

            // ==========================================
            // Sécurité (reCAPTCHA, etc.)
            // ==========================================
            [
                'key' => 'recaptcha_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'security',
                'label' => 'reCAPTCHA activé',
                'description' => 'Activer/désactiver la protection reCAPTCHA sur les formulaires',
                'is_public' => true,
            ],
            [
                'key' => 'recaptcha_site_key',
                'value' => '',
                'type' => 'text',
                'group' => 'security',
                'label' => 'Clé du site reCAPTCHA',
                'description' => 'Clé publique reCAPTCHA v3 (visible côté client)',
                'is_public' => true,
            ],
            [
                'key' => 'recaptcha_secret_key',
                'value' => '',
                'type' => 'text',
                'group' => 'security',
                'label' => 'Clé secrète reCAPTCHA',
                'description' => 'Clé privée reCAPTCHA v3 (côté serveur uniquement)',
                'is_public' => false,
            ],
            [
                'key' => 'recaptcha_min_score',
                'value' => '0.5',
                'type' => 'text',
                'group' => 'security',
                'label' => 'Score minimum reCAPTCHA',
                'description' => 'Score minimum pour valider une requête (0.0 - 1.0)',
                'is_public' => false,
            ],
            [
                'key' => 'recaptcha_forms',
                'value' => '["contact", "login", "register"]',
                'type' => 'json',
                'group' => 'security',
                'label' => 'Formulaires protégés',
                'description' => 'Liste des formulaires protégés par reCAPTCHA',
                'is_public' => true,
            ],

            // ==========================================
            // Email / SMTP Configuration
            // ==========================================
            [
                'key' => 'mail_mailer',
                'value' => env('MAIL_MAILER', 'smtp'),
                'type' => 'select',
                'group' => 'email',
                'label' => 'Type de service mail',
                'description' => 'Service d\'envoi d\'emails (smtp, mailgun, sendgrid, ses)',
                'is_public' => false,
            ],
            [
                'key' => 'mail_host',
                'value' => env('MAIL_HOST', 'smtp.example.com'),
                'type' => 'text',
                'group' => 'email',
                'label' => 'Serveur SMTP',
                'description' => 'Adresse du serveur SMTP',
                'is_public' => false,
            ],
            [
                'key' => 'mail_port',
                'value' => (string) env('MAIL_PORT', '587'),
                'type' => 'number',
                'group' => 'email',
                'label' => 'Port SMTP',
                'description' => 'Port du serveur SMTP (25, 465, 587, 2525)',
                'is_public' => false,
            ],
            [
                'key' => 'mail_username',
                'value' => env('MAIL_USERNAME', ''),
                'type' => 'text',
                'group' => 'email',
                'label' => 'Utilisateur SMTP',
                'description' => 'Nom d\'utilisateur pour l\'authentification SMTP',
                'is_public' => false,
            ],
            [
                'key' => 'mail_password',
                'value' => env('MAIL_PASSWORD', ''),
                'type' => 'password',
                'group' => 'email',
                'label' => 'Mot de passe SMTP',
                'description' => 'Mot de passe pour l\'authentification SMTP',
                'is_public' => false,
            ],
            [
                'key' => 'mail_encryption',
                'value' => env('MAIL_ENCRYPTION', 'tls'),
                'type' => 'select',
                'group' => 'email',
                'label' => 'Chiffrement',
                'description' => 'Type de chiffrement (tls, ssl, null)',
                'is_public' => false,
            ],
            [
                'key' => 'mail_from_address',
                'value' => env('MAIL_FROM_ADDRESS', 'noreply@madibabtp.com'),
                'type' => 'email',
                'group' => 'email',
                'label' => 'Email expéditeur',
                'description' => 'Adresse email par défaut pour l\'envoi',
                'is_public' => false,
            ],
            [
                'key' => 'mail_from_name',
                'value' => env('MAIL_FROM_NAME', env('APP_NAME', 'MBC - Madiba Building Construction')),
                'type' => 'text',
                'group' => 'email',
                'label' => 'Nom expéditeur',
                'description' => 'Nom affiché comme expéditeur des emails',
                'is_public' => false,
            ],

            // ==========================================
            // Configuration Moneroo (Paiements)
            // ==========================================
            [
                'key' => 'moneroo_secret_key',
                'value' => '',
                'type' => 'password',
                'group' => 'payment',
                'label' => 'Clé secrète Moneroo',
                'description' => 'Clé API secrète Moneroo (pvk_xxx ou pvk_live_xxx)',
                'is_public' => false,
            ],
            [
                'key' => 'moneroo_test_mode',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'payment',
                'label' => 'Mode test Moneroo',
                'description' => 'Activer le mode sandbox pour les tests',
                'is_public' => false,
            ],
            [
                'key' => 'moneroo_currency',
                'value' => 'XAF',
                'type' => 'select',
                'group' => 'payment',
                'label' => 'Devise par défaut',
                'description' => 'Devise pour les paiements (XAF, XOF, USD, EUR)',
                'is_public' => false,
            ],
            [
                'key' => 'payment_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'payment',
                'label' => 'Paiements activés',
                'description' => 'Activer/désactiver les paiements en ligne',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $setting) {
            SiteSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('✓ Site settings seeded successfully');
    }
}
