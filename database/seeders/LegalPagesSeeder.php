<?php

namespace Database\Seeders;

use App\Models\LegalPage;
use Illuminate\Database\Seeder;

class LegalPagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $legalPages = [
            [
                'slug' => 'mentions-legales',
                'title' => 'Mentions Légales',
                'subtitle' => 'Informations légales et éditoriales',
                'meta_title' => 'Mentions Légales | MBC',
                'meta_description' => 'Mentions légales de la plateforme MBC, informations d’édition, exploitation et contact.',
                'last_updated' => '2026-04-06',
                'content' => $this->getMentionsLegalesContent(),
            ],
            [
                'slug' => 'cgu',
                'title' => "Conditions Générales d'Utilisation",
                'subtitle' => 'Site public, espaces privés et services numériques MBC',
                'meta_title' => "Conditions Générales d'Utilisation | MBC",
                'meta_description' => "Conditions Générales d'Utilisation de la plateforme MBC, applicables au site public, aux comptes et aux espaces métiers.",
                'last_updated' => '2026-04-06',
                'content' => $this->getCguContent(),
            ],
            [
                'slug' => 'cgv',
                'title' => 'Conditions Générales de Vente',
                'subtitle' => 'Formations, devis, paiements et certificats',
                'meta_title' => 'Conditions Générales de Vente | MBC',
                'meta_description' => 'Conditions Générales de Vente applicables aux formations, paiements, devis, projets et certificats gérés via la plateforme MBC.',
                'last_updated' => '2026-04-06',
                'content' => $this->getCgvContent(),
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Politique de Confidentialité',
                'subtitle' => 'Données personnelles, sécurité et cookies',
                'meta_title' => 'Politique de Confidentialité | MBC',
                'meta_description' => 'Politique de confidentialité de MBC relative aux comptes, inscriptions, paiements, certificats, cookies et services numériques.',
                'last_updated' => '2026-04-06',
                'content' => $this->getPrivacyPolicyContent(),
            ],
        ];

        foreach ($legalPages as $page) {
            LegalPage::updateOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }

    private function getMentionsLegalesContent(): string
    {
        return <<<'HTML'
<h2>1. Éditeur de la plateforme</h2>
<div class="legal-block">
    <p><strong>Madiba Building Construction SARL (MBC)</strong></p>
    <p>Forme juridique : Société à Responsabilité Limitée (SARL)</p>
    <p>Activité principale : Travaux de bâtiments et génie civil, services associés et formations professionnelles</p>
    <p>Siège social : Douala, LOGBESSOU, Cameroun</p>
    <p>RCCM : CM-DLA-01-2025-B-01235</p>
    <p>NIU : M092512806Z3W</p>
    <p>Régime fiscal : IGS</p>
</div>

<h2>2. Contact</h2>
<div class="legal-block">
    <p>Email principal : <a href="mailto:contact@madibabc.com">contact@madibabc.com</a></p>
    <p>Email administratif : <a href="mailto:admin@madibabc.com">admin@madibabc.com</a></p>
    <p>Email formation : <a href="mailto:formations@madibabc.com">formations@madibabc.com</a></p>
    <p>Téléphone : +237 692 65 35 90</p>
</div>

<h2>3. Périmètre du site et de la plateforme</h2>
<p>Les présentes mentions légales s'appliquent à la plateforme web MBC, à son site public, à ses API, à ses interfaces d'inscription, à ses espaces privés apprenant, client, formateur, secrétaire, chef de chantier et administrateur, ainsi qu'aux pages publiques de vérification des certificats.</p>

<h2>4. Direction de publication et exploitation</h2>
<p>La publication et l'exploitation de la plateforme sont assurées par MBC et toute personne dûment mandatée par celle-ci pour l'administration fonctionnelle, éditoriale, commerciale ou technique du service.</p>

<h2>5. Hébergement et infrastructure</h2>
<p>La plateforme est exploitée sur des infrastructures techniques sélectionnées par MBC et ses prestataires. Les composants d'hébergement, de messagerie, d'analyse d'audience, de sécurité et de paiement peuvent être fournis par des sous-traitants techniques distincts selon les besoins du service.</p>

<div class="legal-note">
    <p><strong>Important :</strong> certains modules peuvent appeler des services tiers, notamment pour le paiement en ligne, la protection reCAPTCHA, l'envoi d'emails, l'analyse d'audience ou la vérification d'un certificat.</p>
</div>

<h2>6. Propriété intellectuelle</h2>
<p>Sauf mention contraire, l'ensemble des contenus présents sur la plateforme MBC, y compris les textes, visuels, logos, éléments graphiques, interfaces, modèles documentaires, bases de données, certificats, reçus, rapports, codes sources et éléments logiciels, est protégé par les règles applicables en matière de propriété intellectuelle.</p>
<p>Toute reproduction, extraction, diffusion, adaptation ou utilisation non autorisée, totale ou partielle, est interdite sans accord préalable écrit de MBC.</p>

<h2>7. Disponibilité</h2>
<p>MBC s'efforce d'assurer une disponibilité raisonnable de la plateforme, sans garantie d'accès permanent ni d'absence d'erreur. Des interruptions peuvent intervenir pour maintenance, sécurité, mise à jour, incident technique, indisponibilité réseau ou cas de force majeure.</p>

<h2>8. Référencement et liens externes</h2>
<p>Le site peut contenir des liens vers des services tiers ou contenus externes. MBC n'exerce pas de contrôle sur ces sites tiers et ne saurait être tenue responsable de leur contenu, de leur disponibilité ou de leurs pratiques.</p>

<h2>9. Documents contractuels associés</h2>
<p>L'utilisation de la plateforme est complétée par les documents suivants :</p>
<ul>
    <li>les Conditions Générales d'Utilisation ;</li>
    <li>les Conditions Générales de Vente ;</li>
    <li>la Politique de Confidentialité.</li>
</ul>

<h2>10. Droit applicable</h2>
<p>Les présentes mentions légales sont interprétées conformément au droit applicable en République du Cameroun, sous réserve des règles impératives éventuellement applicables à l'activité exercée par MBC et aux services proposés via la plateforme.</p>
HTML;
    }

    private function getCguContent(): string
    {
        return <<<'HTML'
<h2>1. Objet</h2>
<p>Les présentes Conditions Générales d'Utilisation définissent les règles applicables à l'accès et à l'utilisation de la plateforme numérique MBC, comprenant notamment le site public, les formulaires de contact et de devis, les inscriptions en ligne aux formations, les espaces utilisateurs sécurisés, les pages de paiement, les reçus, les certificats et la vérification publique des certificats.</p>

<h2>2. Acceptation</h2>
<p>L'utilisation de la plateforme implique l'acceptation des présentes CGU, de la Politique de Confidentialité, des Mentions Légales et, lorsque le parcours comporte une commande ou un paiement, des CGV.</p>
<p>L'utilisateur reconnaît que certaines opérations sensibles, notamment l'inscription d'un compte ou la connexion à un espace privé, peuvent être conditionnées à une confirmation explicite de ces documents.</p>

<h2>3. Accès au site et aux espaces privés</h2>
<p>La plateforme comporte un espace public accessible sans compte et des espaces privés réservés aux utilisateurs autorisés. L'accès à certaines fonctionnalités nécessite la création d'un compte ou l'attribution préalable d'un rôle par MBC.</p>
<p>Les principaux rôles actuellement gérés dans l'application sont notamment :</p>
<ul>
    <li>apprenant ;</li>
    <li>client ;</li>
    <li>formateur ;</li>
    <li>secrétaire ;</li>
    <li>chef de chantier ;</li>
    <li>administrateur.</li>
</ul>

<h2>4. Création et sécurité du compte</h2>
<p>L'utilisateur s'engage à fournir des informations exactes, à jour et non trompeuses lors de la création de son compte ou de la mise à jour de son profil. Il est responsable de la confidentialité de ses identifiants et de toute activité réalisée depuis son compte.</p>
<p>MBC peut mettre en place des mécanismes complémentaires de sécurité, tels qu'une vérification par email, un code temporaire de connexion, des journaux de connexion, des contrôles de sécurité et des dispositifs anti-abus.</p>

<div class="legal-note">
    <p><strong>Règle de sécurité :</strong> l'utilisateur doit informer MBC sans délai en cas d'utilisation non autorisée, de suspicion d'accès frauduleux ou de compromission de ses identifiants.</p>
</div>

<h2>5. Utilisation autorisée</h2>
<p>L'utilisateur s'engage à utiliser la plateforme de bonne foi et conformément à sa destination. Sont notamment interdits :</p>
<ul>
    <li>tout accès non autorisé à un espace, une ressource, un document ou une donnée ;</li>
    <li>toute tentative de perturbation, d'altération, d'extraction massive ou d'attaque contre le service ;</li>
    <li>toute usurpation d'identité, falsification de données ou soumission d'informations mensongères ;</li>
    <li>toute reproduction non autorisée des contenus, supports, modèles ou documents diffusés par MBC ;</li>
    <li>tout usage illicite, frauduleux, diffamatoire, injurieux ou portant atteinte aux droits de tiers.</li>
</ul>

<h2>6. Formulaires publics, devis et demandes</h2>
<p>Les formulaires publics de contact, de demande de devis ou de prise de contact doivent être utilisés uniquement pour des demandes réelles et sérieuses. L'utilisateur s'engage à ne pas détourner ces formulaires à des fins de spam, de nuisance ou de démarchage non sollicité.</p>
<p>MBC peut appliquer des mécanismes de limitation, de vérification reCAPTCHA ou de filtrage pour protéger ces formulaires.</p>

<h2>7. Inscriptions aux formations et parcours apprenant</h2>
<p>La plateforme permet la consultation des formations, la soumission d'une inscription avec ou sans compte, le suivi d'inscription, le paiement, l'accès à l'espace apprenant, le téléchargement de reçus et, lorsqu'ils sont émis, l'accès aux certificats.</p>
<p>Une demande d'inscription ne vaut pas automatiquement admission définitive ni délivrance d'un certificat. Certaines étapes restent soumises à la disponibilité des sessions, à la validation du paiement et aux validations internes prévues par MBC.</p>

<h2>8. Espaces clients et chantiers</h2>
<p>L'espace client permet notamment de consulter des projets, l'avancement de chantier, des documents, des messages, des devis et des paiements associés. Les informations publiées dans ces espaces ont une valeur de suivi opérationnel et d'information, sans remplacer à elles seules les documents contractuels, devis signés, bons de commande, rapports techniques ou correspondances formelles.</p>

<h2>9. Paiements, reçus et documents</h2>
<p>La plateforme peut proposer des liens de paiement, des pages de reprise de paiement, des reçus PDF, des justificatifs ou d'autres documents générés automatiquement ou manuellement par MBC. L'utilisateur s'engage à n'utiliser que ses propres liens, références et documents, ou ceux qu'il est habilité à consulter.</p>

<h2>10. Certificats et vérification publique</h2>
<p>Lorsqu'un certificat est émis par MBC, il est rattaché à une formation ou une session précise et peut faire l'objet d'une vérification publique via une référence unique. Cette vérification permet à un tiers disposant de la référence de consulter certaines informations limitées relatives à l'authenticité du certificat.</p>
<p>MBC se réserve le droit de refuser, suspendre, invalider ou révoquer un certificat en cas d'erreur, de fraude, de retrait de validation pédagogique ou administrative, ou de toute autre cause légitime liée à l'authenticité du document.</p>

<h2>11. Disponibilité et maintenance</h2>
<p>MBC ne garantit pas une disponibilité continue et ininterrompue du service. La plateforme peut être suspendue ou limitée pour maintenance, correction, évolution, incident, cybersécurité, saturation, panne de prestataire tiers ou force majeure.</p>

<h2>12. Propriété intellectuelle</h2>
<p>Les interfaces, bases, modèles, documents, rapports, reçus, certificats, illustrations, logos, marques, textes, photographies, vidéos, supports pédagogiques et plus largement l'ensemble des éléments de la plateforme demeurent la propriété de MBC ou de ses partenaires, sauf mention contraire.</p>

<h2>13. Données personnelles</h2>
<p>Les traitements de données à caractère personnel réalisés via la plateforme sont décrits dans la Politique de Confidentialité. L'utilisateur est invité à la consulter avant toute utilisation d'un formulaire, création de compte ou connexion.</p>

<h2>14. Suspension, restriction et suppression</h2>
<p>MBC peut suspendre, restreindre ou supprimer l'accès à tout ou partie de la plateforme en cas de non-respect des présentes CGU, d'incident de sécurité, de tentative de fraude, d'usage abusif, de comportement portant atteinte au service ou de demande émanant d'une autorité compétente.</p>
<p>La suppression volontaire d'un compte n'emporte pas nécessairement suppression immédiate de toutes les données ou documents devant être conservés pour des raisons légales, comptables, probatoires, administratives ou de sécurité.</p>

<h2>15. Modification des CGU</h2>
<p>MBC peut mettre à jour les présentes CGU à tout moment pour refléter l'évolution du service, des fonctionnalités, des processus métier ou des obligations applicables. La version en vigueur est celle publiée sur la plateforme à la date de consultation.</p>

<h2>16. Réclamations et droit applicable</h2>
<p>Pour toute difficulté liée à l'utilisation de la plateforme, l'utilisateur peut contacter MBC via les coordonnées figurant dans les Mentions Légales. Les présentes CGU sont régies par le droit applicable en République du Cameroun, sous réserve des dispositions impératives applicables.</p>
HTML;
    }

    private function getCgvContent(): string
    {
        return <<<'HTML'
<h2>1. Objet et champ d'application</h2>
<p>Les présentes Conditions Générales de Vente s'appliquent aux prestations et opérations commerciales proposées ou suivies via la plateforme MBC, notamment :</p>
<ul>
    <li>les inscriptions aux formations ;</li>
    <li>les paiements de frais d'inscription, de services ou de projets ;</li>
    <li>les demandes de devis et réponses commerciales ;</li>
    <li>les paiements liés aux projets ou chantiers ;</li>
    <li>la génération de reçus et de documents justificatifs ;</li>
    <li>la délivrance et la gestion des certificats de formation.</li>
</ul>

<h2>2. Informations commerciales</h2>
<p>Les prix, frais, modalités, disponibilités et informations affichés sur la plateforme ou transmis dans un devis, une proposition, un message ou un document commercial sont communiqués sous réserve de mise à jour, de disponibilité, de validation commerciale et de confirmation par MBC.</p>
<p>Les tarifs applicables sont ceux affichés ou communiqués au moment de la demande ou de l'opération, sauf stipulation contraire dans un document contractuel distinct.</p>

<h2>3. Inscriptions aux formations</h2>
<h3>3.1 Parcours d'inscription</h3>
<p>Une formation peut être demandée en ligne depuis la plateforme avec ou sans compte utilisateur, selon le parcours proposé. L'inscription peut être associée à une session précise lorsqu'une session est disponible.</p>
<p>La simple soumission du formulaire n'emporte pas automatiquement validation définitive de l'inscription. L'inscription demeure dépendante de la disponibilité, du paiement attendu et des contrôles internes nécessaires.</p>

<h3>3.2 Frais d'inscription et fenêtre de paiement</h3>
<p>Pour certaines formations, la plateforme peut générer un paiement ou un lien de paiement relatif aux frais d'inscription. Ce lien peut être limité dans le temps. En cas de non-paiement dans le délai prévu, la demande peut rester en attente, expirer ou être annulée, avec nécessité de reprendre ou de reformuler la demande.</p>

<h3>3.3 Confirmation</h3>
<p>Une inscription à une formation n'est considérée comme confirmée qu'après validation effective du paiement attendu et confirmation de l'enregistrement par MBC, selon le processus applicable à la formation concernée.</p>

<h2>4. Paiements</h2>
<p>La plateforme peut proposer différents moyens de paiement en ligne ou faire intervenir des enregistrements manuels par l'administration de MBC. Selon les cas, les moyens proposés peuvent notamment inclure Orange Money, MTN Mobile Money, Wave, carte bancaire ou tout autre moyen activé par MBC ou son prestataire de paiement.</p>
<p>Les paiements en ligne peuvent être opérés au moyen d'un prestataire spécialisé, actuellement intégré dans la plateforme. L'utilisateur est responsable de l'exactitude des informations saisies pour réaliser le paiement.</p>

<div class="legal-note">
    <p><strong>Attention :</strong> un paiement initié, incomplet ou échoué peut rester en attente et faire l'objet d'un lien de reprise. La présence d'une référence ou d'un lien ne vaut pas validation définitive tant que le paiement n'est pas confirmé.</p>
</div>

<h2>5. Codes promotionnels</h2>
<p>Lorsqu'un code promotionnel est proposé par MBC, son utilisation reste soumise aux conditions qui lui sont propres : période de validité, montant minimal, formation ou opération éligible, unicité d'usage ou limitation quantitative. MBC peut refuser tout code expiré, invalide, détourné ou utilisé en contradiction avec ses règles.</p>

<h2>6. Devis, projets et chantiers</h2>
<p>Les demandes de devis saisies sur la plateforme permettent à MBC d'analyser un besoin et, le cas échéant, de transmettre une réponse commerciale ou un document de devis. Un devis ne vaut engagement définitif qu'après acceptation par les parties selon les modalités convenues.</p>
<p>Pour les projets et chantiers, les modalités précises de prix, d'échelonnement, d'exécution, de calendrier, d'acompte, de réception, de facturation et de responsabilité peuvent être définies dans des documents distincts : devis signé, bon de commande, contrat, avenant, facture ou document de cadrage.</p>

<h2>7. Annulation, report et remboursement</h2>
<p>Sauf clause contraire expressément convenue par écrit, toute demande d'annulation, de report, d'avoir ou de remboursement est examinée par MBC au regard de la nature de l'opération, de l'état d'avancement du dossier, des coûts déjà engagés, des disponibilités mobilisées et des documents contractuels applicables.</p>
<p>En matière de formation, MBC se réserve le droit de proposer, selon les cas, un report, un maintien de la somme en acompte, un remboursement partiel ou aucune restitution lorsque le service a déjà été engagé, que la session a été réservée ou que des frais irréversibles ont été supportés.</p>

<h2>8. Reçus, justificatifs et factures</h2>
<p>Lorsqu'un paiement est validé, la plateforme peut générer un reçu, un justificatif ou un document téléchargeable. Ces documents sont émis sur la base des informations connues dans la plateforme au moment de la génération. Il appartient au client ou à l'utilisateur de signaler sans délai toute erreur matérielle constatée.</p>

<h2>9. Certificats de formation</h2>
<p>La délivrance d'un certificat n'est pas automatique du seul fait de l'inscription à une formation. Elle suppose que la formation concernée ait été effectivement achevée et que la fin réelle du parcours ait été validée selon le processus interne de MBC.</p>
<p>Dans le fonctionnement actuel de la plateforme, une demande de certificat peut être initiée par le formateur pour un apprenant donné et pour une formation précise, puis soumise à une validation du secrétariat ou de l'administration avant génération du certificat.</p>
<p>Le certificat, une fois approuvé, est généré automatiquement, rendu visible dans l'espace apprenant et peut être vérifié publiquement via une référence dédiée. MBC conserve la faculté de l'invalider ou de le révoquer si la situation l'exige.</p>

<h2>10. Responsabilité</h2>
<p>MBC est tenue d'une obligation de moyens pour l'exploitation de sa plateforme, la gestion des demandes, la mise à disposition des paiements, la production de documents et le suivi de ses services numériques. Sa responsabilité ne saurait être engagée pour les dommages indirects, pertes d'opportunité, indisponibilités réseau, incidents chez un prestataire tiers, erreurs imputables à l'utilisateur ou cas de force majeure.</p>

<h2>11. Réclamations</h2>
<p>Toute réclamation relative à une commande, une inscription, un paiement, un devis, un projet, un reçu ou un certificat peut être adressée par écrit à MBC via les coordonnées figurant dans les Mentions Légales, en indiquant la référence utile, les faits, la date et tout document justificatif disponible.</p>

<h2>12. Droit applicable et règlement des différends</h2>
<p>Les présentes CGV sont soumises au droit applicable en République du Cameroun, sous réserve des règles impératives applicables. Les parties privilégieront une tentative de résolution amiable avant toute action contentieuse.</p>
HTML;
    }

    private function getPrivacyPolicyContent(): string
    {
        return <<<'HTML'
<h2>1. Responsable du traitement</h2>
<div class="legal-block">
    <p><strong>Madiba Building Construction SARL (MBC)</strong></p>
    <p>Siège social : Douala, LOGBESSOU, Cameroun</p>
    <p>Email principal : <a href="mailto:admin@madibabc.com">admin@madibabc.com</a></p>
    <p>Email contact : <a href="mailto:contact@madibabc.com">contact@madibabc.com</a></p>
</div>

<h2>2. Données concernées</h2>
<p>Selon les fonctionnalités utilisées, MBC peut traiter les catégories de données suivantes :</p>
<ul>
    <li>données d'identité et de contact : nom, prénom, email, téléphone, société, adresse ;</li>
    <li>données de compte : identifiants, rôle, historique de connexion, préférences, profil ;</li>
    <li>données liées aux demandes de contact ou de devis : sujet, type de service, message, documents de réponse, numéro de devis ;</li>
    <li>données liées aux inscriptions formation : formation, session, message, statut, éléments de suivi, données invitées ou rattachées à un compte ;</li>
    <li>données de paiement : montant, devise, référence, moyen de paiement, statut, code promotionnel, reçu, justificatifs, liens de paiement ;</li>
    <li>données de sécurité : adresse IP, user-agent, défi de connexion, empreinte de connexion, journaux techniques, envois liés à l'authentification ;</li>
    <li>données liées aux certificats : référence, statut, dates utiles, formation concernée, formateur, historique d'émission ou de révocation ;</li>
    <li>données de navigation et cookies selon les réglages actifs de la plateforme.</li>
</ul>

<h2>3. Sources des données</h2>
<p>Les données sont collectées directement auprès de la personne concernée lorsqu'elle remplit un formulaire, crée un compte, se connecte, effectue un paiement, consulte un espace privé, demande une formation, télécharge un document ou utilise une page publique de vérification.</p>
<p>Certaines données peuvent également être générées automatiquement par la plateforme ou ses prestataires techniques, notamment les journaux de sécurité, l'état des paiements, les traces de connexion ou les métadonnées techniques.</p>

<h2>4. Finalités du traitement</h2>
<p>MBC traite les données personnelles notamment pour :</p>
<ul>
    <li>répondre aux demandes de contact, de devis et d'information ;</li>
    <li>créer, administrer et sécuriser les comptes utilisateurs ;</li>
    <li>gérer les rôles et l'accès aux espaces apprenant, client, formateur, secrétaire, chef de chantier ou administrateur ;</li>
    <li>gérer les inscriptions aux formations et les parcours apprenants ;</li>
    <li>initier, vérifier, relancer et tracer les paiements ;</li>
    <li>générer et mettre à disposition des reçus, justificatifs, devis, réponses et certificats ;</li>
    <li>permettre la vérification publique de l'authenticité d'un certificat ;</li>
    <li>protéger la plateforme contre la fraude, l'accès abusif, le spam et les usages illicites ;</li>
    <li>mesurer l'audience ou activer des traceurs optionnels lorsque l'utilisateur y consent ;</li>
    <li>respecter les obligations administratives, comptables, contractuelles et probatoires applicables à MBC.</li>
</ul>

<h2>5. Bases de traitement</h2>
<p>Les traitements sont réalisés selon les cas pour :</p>
<ul>
    <li>l'exécution d'une demande formulée par l'utilisateur ou d'une relation contractuelle ;</li>
    <li>le respect d'obligations légales, comptables, administratives ou de sécurité ;</li>
    <li>l'intérêt légitime de MBC à administrer, sécuriser, améliorer et prouver ses services ;</li>
    <li>le consentement de l'utilisateur lorsqu'il est requis, notamment pour certains cookies ou traceurs optionnels.</li>
</ul>

<h2>6. Destinataires des données</h2>
<p>Les données peuvent être accessibles, selon leur besoin d'en connaître, aux équipes habilitées de MBC et à ses sous-traitants techniques ou prestataires intervenant pour :</p>
<ul>
    <li>l'hébergement et l'exploitation technique ;</li>
    <li>la messagerie et l'envoi d'emails ;</li>
    <li>la protection anti-abus et reCAPTCHA ;</li>
    <li>le traitement ou la vérification des paiements ;</li>
    <li>les outils d'analyse d'audience ou de marketing, lorsqu'ils sont activés ;</li>
    <li>la génération documentaire ou les opérations de support et de maintenance.</li>
</ul>

<h2>7. Cookies et traceurs</h2>
<p>La plateforme distingue actuellement plusieurs catégories :</p>
<ul>
    <li><strong>cookies strictement nécessaires</strong> : indispensables au fonctionnement, à la sécurité, à la navigation ou à la mémorisation de certains choix ;</li>
    <li><strong>cookies analytiques</strong> : utilisés uniquement si l'utilisateur les accepte ;</li>
    <li><strong>cookies marketing</strong> : utilisés uniquement si l'utilisateur les accepte et si les modules correspondants sont activés.</li>
</ul>
<p>Les préférences de cookies sont actuellement mémorisées localement dans le navigateur de l'utilisateur afin de respecter ses choix sur le site concerné.</p>

<div class="legal-note">
    <p><strong>Important :</strong> si les modules analytiques ou marketing ne sont pas activés dans la configuration du site, aucun traceur optionnel correspondant n'est chargé, même si la catégorie existe dans l'interface de préférences.</p>
</div>

<h2>8. Vérification publique des certificats</h2>
<p>Lorsqu'un certificat est émis par MBC, une page publique de vérification peut être accessible via une référence unique. Toute personne disposant de cette référence peut consulter certaines informations limitées liées à l'authenticité du certificat, notamment le nom du titulaire, l'intitulé de la formation, le formateur, certaines dates et le statut du certificat.</p>
<p>Cette fonctionnalité constitue un traitement spécifique destiné à permettre la preuve d'authenticité du document et à limiter les fraudes documentaires.</p>

<h2>9. Durée de conservation</h2>
<p>MBC conserve les données pendant la durée nécessaire aux finalités poursuivies, augmentée, le cas échéant, des délais requis pour la preuve, la gestion administrative, la sécurité, la comptabilité, la défense de ses droits ou le respect de ses obligations légales.</p>
<p>À titre indicatif :</p>
<ul>
    <li>les demandes de contact et de devis sont conservées pendant le temps nécessaire à leur traitement et au suivi commercial ;</li>
    <li>les données de compte sont conservées tant que le compte demeure actif, puis archivées ou supprimées selon leur utilité résiduelle ;</li>
    <li>les données de paiement et les reçus sont conservés pendant les délais nécessaires aux obligations comptables et probatoires ;</li>
    <li>les données de sécurité, journaux de connexion et défis temporaires sont conservés pour la durée utile à la prévention et à l'analyse des incidents ;</li>
    <li>les données de certificat peuvent être conservées aussi longtemps que nécessaire pour garantir la vérification et la traçabilité de l'authenticité du document.</li>
</ul>

<h2>10. Sécurité</h2>
<p>MBC met en œuvre des mesures techniques et organisationnelles raisonnables pour protéger les données contre la perte, l'accès non autorisé, l'altération, la divulgation abusive ou l'usage frauduleux. Toutefois, aucun système n'offrant une sécurité absolue, l'utilisateur est invité à adopter lui-même de bonnes pratiques de sécurité.</p>

<h2>11. Droits des personnes concernées</h2>
<p>La personne concernée peut solliciter, dans les limites prévues par les textes applicables et sous réserve des contraintes légales ou probatoires, l'accès à ses données, leur rectification, leur mise à jour, leur suppression ou la limitation de certains traitements. Elle peut également demander des précisions sur les traitements réalisés par MBC.</p>
<p>Toute demande doit être accompagnée d'éléments permettant une identification raisonnable du demandeur afin de protéger les données contre tout accès indu.</p>

<h2>12. Contact et réclamations</h2>
<p>Pour toute question relative aux données personnelles, à la sécurité, aux certificats ou aux cookies, vous pouvez écrire à <a href="mailto:admin@madibabc.com">admin@madibabc.com</a> ou à <a href="mailto:contact@madibabc.com">contact@madibabc.com</a>.</p>

<h2>13. Mise à jour de la politique</h2>
<p>La présente politique peut être modifiée à tout moment afin de refléter l'évolution de la plateforme, des processus métiers, des prestataires techniques ou des obligations applicables. La version publiée sur la plateforme est la version de référence.</p>
HTML;
    }
}
