# Reverse engineering UML - MBC API

Sources analysees
- Dump SQL : `C:\Users\wulfr\Downloads\mbcapi.sql`
- Modeles Eloquent : `app/Models`
- Routes API : `routes/api.php`
- Controleurs et services metier : `app/Http/Controllers/Api`, `app/Services`

Livrables
- `class_diagram_mbcapi.puml` : diagramme de classes UML consolide
- `use_cases_mbcapi.puml` : diagramme de cas d'utilisation
- `sequence_public_training_enrollment.puml`
- `sequence_manual_payment_validation.puml`
- `sequence_project_phase_transition.puml`
- `sequence_safety_incident_reporting.puml`
- `sequence_staff_invitation_activation.puml`

Cadre d'interpretation
- Le dump SQL contient `42` tables metier et techniques.
- Les tables du dump sont en `MyISAM`, donc sans integrite referentielle effectivement appliquee par le moteur.
- Le dump SQL observe bien la structure physique, mais il n'embarque pas de contraintes `FOREIGN KEY` explicites exploitables dans le fichier exporte. Les cardinalites ont donc ete consolidees a partir des noms d'index et surtout des relations Eloquent.
- Les diagrammes de classes sont majoritairement observes.
- Les diagrammes de cas d'utilisation et de sequence sont semi-observes : ils s'appuient sur les routes, controleurs et services reels, mais restent une modelisation UML du comportement.

Sous-domaines metier identifies
- Identite et acces : utilisateurs, roles, multi-role, invitations, journal d'activites
- Catalogue public : services, portfolio, equipe, pages legales, parametres publics
- Formation : formations, sessions, inscriptions, presences, evaluations, resultats
- Paiement : paiements polymorphes, codes promo, usages, recus, rapports financiers
- Chantier : double noyau `projects` / `project_phases` / `equipment` et `portfolio_projects` / `project_updates` / `progress_updates`
- Relation client & publication : portfolio, temoignages, contact, messagerie

Incoherences importantes reperees pendant le reverse engineering
- `formation_enrollments` expose la colonne `session_id`, mais certaines portions de code utilisent `formation_session_id`.
- Deux modeles coexistent pour les avancements chantier :
  - `ProgressUpdate` colle au dump SQL
  - `ProjectUpdate` est utilise par `ChefChantierController` et `ChantierService`
- `media` contient `portfolio_project_id` dans le dump, alors que `Media::portfolioProject()` pointe vers `portfolio_id`.
- `daily_logs` et `safety_incidents` ont plus d'attributs dans les modeles/services que dans le dump exporte, ce qui suggere un decrochage entre schema attendu par le code et schema contenu dans le dump.
- La table `messages` existe, mais le module client envoie ses messages via `contact_requests`, pas via `messages`.

Portee volontairement exclue du diagramme de classes principal
- Tables techniques Laravel : `cache`, `cache_locks`, `jobs`, `job_batches`, `migrations`, `failed_jobs`, `sessions`, `password_reset_tokens`, `personal_access_tokens`, `api_idempotency_keys`

Usage
- Ces fichiers sont ecrits en PlantUML.
- Ils peuvent etre rendus avec PlantUML, IntelliJ, VS Code PlantUML, ou un serveur PlantUML.
