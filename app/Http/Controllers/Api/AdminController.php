<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\LoginHistory;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPassword;

class AdminController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function dashboardStats(): JsonResponse
    {
        // Calculate Revenue (Total completed payments)
        $totalRevenue = \App\Models\Payment::where('status', 'completed')->sum('amount');

        // Calculate Projects (Portfolio)
        $activeProjects = \App\Models\PortfolioProject::count(); // Assuming all are active for now

        // Apprenants Stats
        $totalApprenants = User::whereHas('roles', fn($q) => $q->where('slug', 'apprenant'))->count();

        $stats = [
            'users' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'new_this_month' => User::whereMonth('created_at', now()->month)->count(),
            ],
            'financials' => [
                'total_revenue' => $totalRevenue,
                'monthly_revenue' => \App\Models\Payment::where('status', 'completed')
                    ->whereBetween($this->financialReportDateExpression(), [
                        now()->startOfMonth()->toDateTimeString(),
                        now()->endOfMonth()->toDateTimeString(),
                    ])
                    ->sum('amount'),
            ],
            'projects' => [
                'active' => $activeProjects,
            ],
            'apprenants' => [
                'total' => $totalApprenants,
            ],
            'roles' => [
                'admin' => User::whereHas('roles', fn($q) => $q->where('slug', 'admin'))->count(),
                'staff' => User::whereHas('roles', fn($q) => $q->where('is_staff', true))->count(),
                'clients' => User::whereHas('roles', fn($q) => $q->where('slug', 'client'))->count(),
                'apprenants' => $totalApprenants,
            ],
            'activities' => \App\Models\ActivityLog::important()->with('user')->latest()
                ->take(10)
                ->get()
                ->map(function ($log) {
                    $presentation = $this->resolveActivityPresentation($log);
                    $actorName = $log->user?->name ?? 'Système';
                    $description = $log->description ?: $log->action;

                    if (!str_contains($description, $actorName)) {
                        $description = "{$actorName} - {$description}";
                    }

                    return [
                        'id' => $log->id,
                        'type' => $presentation['type'],
                        'title' => $log->action,
                        'description' => $description,
                        'actor_name' => $actorName,
                        'time' => $log->created_at->diffForHumans(),
                        'status' => $presentation['status']
                    ];
                })
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * List all users with filtering
     */
    public function listUsers(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'activeRole']);

        // Filter by role
        if ($request->has('role') && $request->role) {
            $roleSlug = $request->role;
            if ($roleSlug === 'staff') {
                $query->whereHas('roles', fn($q) => $q->where('is_staff', true));
            } else {
                $query->whereHas('roles', fn($q) => $q->where('slug', $roleSlug));
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $users->setCollection(
            $users->getCollection()->map(fn(User $user) => $user->toExternalArray())
        );

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    /**
     * Get a single user
     */
    public function getUser(string $user): JsonResponse
    {
        $resolvedUser = User::resolvePublicIdOrFail($user);
        $resolvedUser->load(['roles', 'activeRole']);

        return response()->json([
            'success' => true,
            'data' => $resolvedUser->toExternalArray(),
        ]);
    }

    /**
     * Get login history for a specific user (admin only)
     */
    public function getUserLoginHistory(Request $request, string $user): JsonResponse
    {
        $resolvedUser = User::resolvePublicIdOrFail($user);
        $perPage = $request->get('per_page', 15);

        $history = LoginHistory::where('user_id', $resolvedUser->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $resolvedUser->getPublicId(),
                'name' => $resolvedUser->name,
                'email' => $resolvedUser->email,
            ],
            'data' => $history->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    /**
     * Create a new user (admin only)
     */
    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,slug',
            'is_active' => 'boolean',
            // Optional fields
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string',
            'project_type' => 'nullable|string',
            'formation' => 'nullable|string',
            'employee_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'speciality' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
                'company_name' => $validated['company_name'] ?? null,
                'company_address' => $validated['company_address'] ?? null,
                'project_type' => $validated['project_type'] ?? null,
                'formation' => $validated['formation'] ?? null,
                'employee_id' => $validated['employee_id'] ?? null,
                'address' => $validated['address'] ?? null,
                'emergency_contact' => $validated['emergency_contact'] ?? null,
                'emergency_phone' => $validated['emergency_phone'] ?? null,
                'speciality' => $validated['speciality'] ?? null,
                'bio' => $validated['bio'] ?? null,
            ]);

            // Assign roles
            $roles = Role::whereIn('slug', $validated['roles'])->get();
            $firstRole = true;
            foreach ($roles as $role) {
                $user->roles()->attach($role->id, [
                    'is_primary' => $firstRole,
                    'assigned_at' => now(),
                ]);
                if ($firstRole) {
                    $user->active_role_id = $role->id;
                    $firstRole = false;
                }
            }
            $user->save();

            DB::commit();

            $user->load(['roles', 'activeRole']);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur crÃ©Ã© avec succÃ¨s',
                'data' => $user->toExternalArray(),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la crÃ©ation de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a user
     */
    public function updateUser(Request $request, string $user): JsonResponse
    {
        $user = User::resolvePublicIdOrFail($user);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'sometimes|string|max:20',
            'password' => 'sometimes|string|min:8',
            'is_active' => 'sometimes|boolean',
            'roles' => 'sometimes|array|min:1',
            'roles.*' => 'exists:roles,slug',
            // Optional fields
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string',
            'project_type' => 'nullable|string',
            'formation' => 'nullable|string',
            'employee_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'speciality' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Update basic fields
            $updateData = collect($validated)->except(['password', 'roles'])->toArray();
            if (isset($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }
            $user->update($updateData);

            // Update roles if provided
            if (isset($validated['roles'])) {
                $roles = Role::whereIn('slug', $validated['roles'])->get();
                $syncData = [];
                $firstRole = true;
                foreach ($roles as $role) {
                    $syncData[$role->id] = [
                        'is_primary' => $firstRole,
                        'assigned_at' => now(),
                    ];
                    if ($firstRole) {
                        $user->active_role_id = $role->id;
                        $firstRole = false;
                    }
                }
                $user->roles()->sync($syncData);
                $user->save();
            }

            DB::commit();

            $user->load(['roles', 'activeRole']);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis Ã  jour avec succÃ¨s',
                'data' => $user->toExternalArray(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise Ã  jour de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function deleteUser(string $user): JsonResponse
    {
        $user = User::resolvePublicIdOrFail($user);
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte'
            ], 403);
        }

        try {
            // Detach roles
            $user->roles()->detach();
            // Delete user
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimÃ© avec succÃ¨s'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus(string $user): JsonResponse
    {
        $user = User::resolvePublicIdOrFail($user);
        // Prevent deactivating yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas dÃ©sactiver votre propre compte'
            ], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'Utilisateur activÃ©' : 'Utilisateur dÃ©sactivÃ©',
            'data' => [
                'is_active' => $user->is_active
            ]
        ]);
    }

    /**
     * List all roles
     */
    public function listRoles(): JsonResponse
    {
        $roles = Role::withCount('users')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Create a new role
     */
    public function createRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:50|unique:roles,slug|regex:/^[a-z_]+$/',
            'description' => 'nullable|string',
            'is_staff' => 'boolean',
            'can_self_register' => 'boolean',
        ]);

        $role = Role::create($validated);

        ActivityLogService::log(
            action: 'Rôle créé',
            description: "Le rôle '{$role->name}' a été ajouté au système.",
            subject: $role,
            userId: $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'RÃ´le crÃ©Ã© avec succÃ¨s',
            'data' => $role
        ], 201);
    }

    /**
     * Update a role
     */
    public function updateRole(Request $request, Role $role): JsonResponse
    {
        // Prevent updating system roles
        $systemRoles = ['admin', 'secretaire', 'chef_chantier', 'formateur', 'client', 'apprenant'];
        if (in_array($role->slug, $systemRoles) && $request->has('slug')) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier le slug d\'un rÃ´le systÃ¨me'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:50', 'regex:/^[a-z_]+$/', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string',
            'is_staff' => 'sometimes|boolean',
            'can_self_register' => 'sometimes|boolean',
        ]);

        $previousName = $role->name;
        $role->update($validated);

        ActivityLogService::log(
            action: 'Rôle mis à jour',
            description: "Le rôle '{$previousName}' a été mis à jour.",
            subject: $role,
            userId: $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'RÃ´le mis Ã  jour avec succÃ¨s',
            'data' => $role
        ]);
    }

    /**
     * Delete a role
     */
    public function deleteRole(Role $role): JsonResponse
    {
        // Prevent deleting system roles
        $systemRoles = ['admin', 'secretaire', 'chef_chantier', 'formateur', 'client', 'apprenant'];
        if (in_array($role->slug, $systemRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un rÃ´le systÃ¨me'
            ], 403);
        }

        // Check if role is assigned to users
        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un rÃ´le assignÃ© Ã  des utilisateurs'
            ], 400);
        }

        $roleName = $role->name;
        $role->delete();

        ActivityLogService::log(
            action: 'Rôle supprimé',
            description: "Le rôle '{$roleName}' a été supprimé du système.",
            subject: null,
            userId: $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'RÃ´le supprimÃ© avec succÃ¨s'
        ]);
    }

    /**
     * Export users to CSV
     */
    public function exportUsers(Request $request): JsonResponse
    {
        $users = User::with('roles')
            ->when($request->role, function ($q, $role) {
                $q->whereHas('roles', fn($q) => $q->where('slug', $role));
            })
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->getPublicId(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'roles' => $user->roles->pluck('name')->join(', '),
                    'is_active' => $user->is_active ? 'Oui' : 'Non',
                    'created_at' => $user->created_at->format('d/m/Y H:i'),
                    'last_login_at' => $user->last_login_at?->format('d/m/Y H:i') ?? 'Jamais',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
    /**
     * List financial reports
     */
    public function listFinancialReports(Request $request): JsonResponse
    {
        $query = \App\Models\FinancialReport::with('creator');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('period_start')) {
            $query->whereDate('period_start', '>=', $request->period_start);
        }

        $reports = $query->orderByDesc('created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    /**
     * Generate a financial report for a monthly or yearly period.
     */
    public function generateFinancialReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_type' => 'nullable|string|in:monthly,current_year,previous_year',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:2035',
        ]);

        $period = $this->resolveFinancialReportPeriod($validated);
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];

        $payments = \App\Models\Payment::with(['user', 'payable'])
            ->where('status', 'completed')
            ->whereBetween($this->financialReportDateExpression(), [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ])
            ->get();

        $totalRevenue = $payments->sum('amount');
        $count = $payments->count();

        // Group by method
        $byMethod = $payments->groupBy('method')->map->sum('amount');

        // 2. Generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.monthly_financial', [
            'payments' => $payments,
            'totalRevenue' => $totalRevenue,
            'count' => $count,
            'byMethod' => $byMethod,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reportTitle' => $period['title'],
            'periodLabel' => $period['period_label'],
        ]);

        // 3. Save to Storage
        $filename = 'rapport_financier_' . $period['filename_suffix'] . '_' . time() . '.pdf';
        $path = "financial-reports/$filename";
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $pdf->output());

        // 4. Create Record
        $report = \App\Models\FinancialReport::create([
            'title' => $period['title'],
            'period_start' => $startDate,
            'period_end' => $endDate,
            'type' => $period['report_type'],
            'file_path' => $path,
            'generated_by' => auth()->id(),
            'is_auto_generated' => true,
            'status' => 'published',
            'metadata' => [
                'total_revenue' => $totalRevenue,
                'transaction_count' => $count,
                'period_type' => $period['period_type'],
                'report_year' => $period['report_year'],
                'report_month' => $period['report_month'],
                'period_label' => $period['period_label'],
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rapport gÃ©nÃ©rÃ© avec succÃ¨s',
            'data' => $report
        ]);
    }

    private function financialReportDateExpression()
    {
        return DB::raw('COALESCE(validated_at, paid_at, created_at)');
    }

    private function resolveFinancialReportPeriod(array $validated): array
    {
        $periodType = $validated['period_type'] ?? 'monthly';
        $currentYear = now()->year;

        if ($periodType === 'current_year') {
            $startDate = \Carbon\Carbon::createFromDate($currentYear, 1, 1)->startOfYear();
            $endDate = $startDate->copy()->endOfYear();

            return [
                'period_type' => $periodType,
                'report_type' => 'yearly_revenue',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'title' => "Rapport Financier - Année {$currentYear}",
                'filename_suffix' => (string) $currentYear,
                'period_label' => "Année {$currentYear}",
                'report_year' => $currentYear,
                'report_month' => null,
            ];
        }

        if ($periodType === 'previous_year') {
            $previousYear = $currentYear - 1;
            $startDate = \Carbon\Carbon::createFromDate($previousYear, 1, 1)->startOfYear();
            $endDate = $startDate->copy()->endOfYear();

            return [
                'period_type' => $periodType,
                'report_type' => 'yearly_revenue',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'title' => "Rapport Financier - Année {$previousYear}",
                'filename_suffix' => (string) $previousYear,
                'period_label' => "Année {$previousYear}",
                'report_year' => $previousYear,
                'report_month' => null,
            ];
        }

        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? $currentYear);
        $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        return [
            'period_type' => 'monthly',
            'report_type' => 'monthly_revenue',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'title' => 'Rapport Financier - ' . $startDate->translatedFormat('F Y'),
            'filename_suffix' => sprintf('%04d_%02d', $year, $month),
            'period_label' => $startDate->translatedFormat('F Y'),
            'report_year' => $year,
            'report_month' => $month,
        ];
    }

    /**
     * Generate Payment History Report
     */
    public function generatePaymentHistory(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'required|in:pdf,csv,excel'
        ]);

        $query = \App\Models\Payment::with(['user', 'payable']);

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $payments = $query->orderByDesc('created_at')->get();

        if ($request->format === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.payment_history', [
                'payments' => $payments,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ]);
            return $pdf->download('historique_paiements.pdf');
        } else {
            // CSV/Excel
            $headers = [
                "Content-type" => "text/csv",
                "Content-Disposition" => "attachment; filename=historique_paiements.csv",
                "Pragma" => "no-cache",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Expires" => "0"
            ];

            $callback = function () use ($payments) {
                $file = fopen('php://output', 'w');
                // BOM for Excel
                fputs($file, "\xEF\xBB\xBF");

                fputcsv($file, ['ID', 'Reference', 'Date', 'Client', 'Montant', 'Methode', 'Statut', 'Motif']);

                foreach ($payments as $payment) {
                    fputcsv($file, [
                        $payment->id,
                        $payment->reference,
                        $payment->created_at->format('d/m/Y H:i'),
                        $payment->user ? $payment->user->name : 'N/A',
                        $payment->amount,
                        $payment->method,
                        $payment->status,
                        $payment->description ?? ''
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }
    }

    /**
     * Export Clients List
     */
    public function exportClients(Request $request)
    {
        // CSV/Excel
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=clients_mbc.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            // BOM for Excel
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['Nom', 'Email', 'Telephone', 'Entreprise', 'Adresse', 'Date Inscription']);

            $clients = \App\Models\User::where('role', 'client')->cursor();

            foreach ($clients as $client) {
                fputcsv($file, [
                    $client->name,
                    $client->email,
                    $client->phone,
                    $client->company_name ?? '',
                    $client->address ?? '',
                    $client->created_at->format('d/m/Y')
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate Project Progress Report
     */
    public function generateProjectProgress(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'format' => 'required|in:pdf,excel'
        ]);

        $query = \App\Models\PortfolioProject::with('creator');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $projects = $query->orderBy('created_at', 'desc')->get();

        if ($request->format === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.project_progress', [
                'projects' => $projects,
                'status_filter' => $request->status
            ]);
            return $pdf->download('avancement_projets.pdf');
        } else {
            // CSV/Excel
            $headers = [
                "Content-type" => "text/csv",
                "Content-Disposition" => "attachment; filename=avancement_projets.csv",
                "Pragma" => "no-cache",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Expires" => "0"
            ];

            $callback = function () use ($projects) {
                $file = fopen('php://output', 'w');
                fputs($file, "\xEF\xBB\xBF");
                fputcsv($file, ['Projet', 'Client', 'Statut', 'Date DÃ©but', 'Date Fin PrÃ©vue', 'AnnÃ©e']);

                foreach ($projects as $project) {
                    fputcsv($file, [
                        $project->title,
                        $project->client,
                        $project->status,
                        $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('d/m/Y') : 'N/A',
                        $project->expected_end_date ? \Carbon\Carbon::parse($project->expected_end_date)->format('d/m/Y') : 'N/A',
                        $project->year
                    ]);
                }
                fclose($file);
            };
            return response()->stream($callback, 200, $headers);
        }
    }

    /**
     * Generate Project Budget Report
     */
    public function generateProjectBudget(Request $request)
    {
        $validated = $request->validate([
            'year' => 'nullable|integer',
            'format' => 'required|in:pdf,excel'
        ]);

        $query = \App\Models\PortfolioProject::query();

        if ($request->year) {
            $query->where('year', $request->year);
        }

        $projects = $query->orderBy('budget', 'desc')->get();

        if ($request->format === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.project_budget', [
                'projects' => $projects,
                'year_filter' => $request->year
            ]);
            return $pdf->download('budget_projets.pdf');
        } else {
            // CSV/Excel
            $headers = [
                "Content-type" => "text/csv",
                "Content-Disposition" => "attachment; filename=budget_projets.csv",
                "Pragma" => "no-cache",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Expires" => "0"
            ];

            $callback = function () use ($projects) {
                $file = fopen('php://output', 'w');
                fputs($file, "\xEF\xBB\xBF");
                fputcsv($file, ['Projet', 'Client', 'Budget', 'AnnÃ©e', 'Statut']);

                foreach ($projects as $project) {
                    fputcsv($file, [
                        $project->title,
                        $project->client,
                        $project->budget,
                        $project->year,
                        $project->status
                    ]);
                }
                fclose($file);
            };
            return response()->stream($callback, 200, $headers);
        }
    }


    /**
     * Generate User Activity Report
     */
    public function generateUserActivity(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'action' => 'nullable|string',
            'format' => 'required|in:pdf,csv'
        ]);

        $query = \App\Models\ActivityLog::important()->with('user');

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->action) {
            $query->where('action', $request->action);
        }

        $logs = $query->orderByDesc('created_at')->limit(500)->get(); // Limit to avoid massive PDF

        if ($request->format === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.user_activity', [
                'logs' => $logs,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ]);
            return $pdf->download('activite_utilisateurs.pdf');
        } else {
            // CSV
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=activite_utilisateurs.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use ($logs) {
                $file = fopen('php://output', 'w');
                fputs($file, "\xEF\xBB\xBF");
                fputcsv($file, ['Date', 'Utilisateur', 'Action', 'Description', 'IP']);

                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log->created_at->format('d/m/Y H:i:s'),
                        $log->user ? $log->user->name : 'N/A',
                        $log->action,
                        $log->description,
                        $log->ip_address
                    ]);
                }
                fclose($file);
            };
            return response()->stream($callback, 200, $headers);
        }
    }

    /**
     * Generate Training Synthesis Report
     */
    public function generateTrainingSynthesis(Request $request)
    {
        $validated = $request->validate([
            'year' => 'nullable|integer',
            'format' => 'required|in:pdf,excel'
        ]);

        $query = \App\Models\FormationEnrollment::with(['formation', 'user'])
            ->where('status', 'confirmed');

        if ($request->year) {
            $query->whereYear('created_at', $request->year);
        }

        $enrollments = $query->orderByDesc('created_at')->get();
        $totalRevenue = $enrollments->sum('amount_paid');
        $byFormation = $enrollments->groupBy('formation.title')->map->count();

        if ($request->format === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.training_synthesis', [
                'enrollments' => $enrollments,
                'totalRevenue' => $totalRevenue,
                'byFormation' => $byFormation,
                'year' => $request->year
            ]);
            return $pdf->download('synthese_formations.pdf');
        } else {
            // CSV/Excel
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=synthese_formations.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use ($enrollments) {
                $file = fopen('php://output', 'w');
                fputs($file, "\xEF\xBB\xBF");
                fputcsv($file, ['Date', 'Apprenant', 'Formation', 'Montant PayÃ©']);

                foreach ($enrollments as $enrollment) {
                    fputcsv($file, [
                        $enrollment->created_at->format('d/m/Y'),
                        $enrollment->full_name,
                        $enrollment->formation ? $enrollment->formation->title : 'N/A',
                        $enrollment->amount_paid
                    ]);
                }
                fclose($file);
            };
            return response()->stream($callback, 200, $headers);
        }
    }

    /**
     * Generate Personnel Hours (Placeholder)
     */
    public function generatePersonnelHours(Request $request)
    {
        // Placeholder: We don't have a Timesheet model yet.
        // Returning a dummy CSV/PDF for demonstration.
        
        if ($request->format === 'pdf') {
             $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<h1>Rapport Heures du Personnel</h1><p>Module de gestion des temps en cours de dÃ©veloppement.</p>');
             return $pdf->download('heures_personnel.pdf');
        }

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=heures_personnel.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Info']);
            fputcsv($file, ['Module en dÃ©veloppement']);
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get detailed activities log with filters
     */
    public function getActivities(Request $request): JsonResponse
    {
        $range = $request->get('range', '7days');

        $query = \App\Models\ActivityLog::important()->with(['user'])->latest();

        // Filter by date range
        switch ($range) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case '7days':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
            case '30days':
                $query->where('created_at', '>=', now()->subDays(30));
                break;
            case 'all':
                // No date filter
                break;
        }

        // Get activities
        $activities = $query->get()->map(function ($log) {
            $presentation = $this->resolveActivityPresentation($log);

            return [
                'id' => $log->id,
                'type' => $presentation['type'],
                'title' => $log->action,
                'description' => $log->description ?? 'Aucune description',
                'time' => $log->created_at->diffForHumans(),
                'date' => $log->created_at->format('d/m/Y a H:i'),
                'status' => $presentation['status'],
                'user' => $log->user ? $log->user->name : 'Systeme'
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    private function resolveActivityPresentation($log): array
    {
        $type = 'default';
        $status = 'info';

        if ($log->subject_type === \App\Models\User::class) {
            $type = 'user';
        } elseif ($log->subject_type === \App\Models\PortfolioProject::class) {
            $type = 'project';
        } elseif ($log->subject_type === \App\Models\Payment::class) {
            $type = 'payment';
        } elseif ($log->subject_type === \App\Models\Formation::class) {
            $type = 'formation';
        }

        $searchText = strtolower(($log->action ?? '') . ' ' . ($log->description ?? ''));

        if ($type === 'default') {
            if (str_contains($searchText, 'paiement') || str_contains($searchText, 'payment')) {
                $type = 'payment';
            } elseif (
                str_contains($searchText, 'projet')
                || str_contains($searchText, 'chantier')
                || str_contains($searchText, 'portfolio')
            ) {
                $type = 'project';
            } elseif (str_contains($searchText, 'formation')) {
                $type = 'formation';
            } elseif (
                str_contains($searchText, 'utilisateur')
                || str_contains($searchText, 'authentification')
                || str_contains($searchText, 'connexion')
                || str_contains($searchText, 'role')
            ) {
                $type = 'user';
            }
        }

        if (
            str_contains($searchText, 'succes')
            || str_contains($searchText, 'reussi')
            || str_contains($searchText, 'valide')
            || str_contains($searchText, 'complete')
            || str_contains($searchText, 'cree')
        ) {
            $status = 'success';
        } elseif (
            str_contains($searchText, 'attente')
            || str_contains($searchText, 'pending')
            || str_contains($searchText, 'redirection')
        ) {
            $status = 'warning';
        } elseif (
            str_contains($searchText, 'echec')
            || str_contains($searchText, 'erreur')
            || str_contains($searchText, 'refuse')
            || str_contains($searchText, 'unauthorized')
            || str_contains($searchText, 'forbidden')
            || str_contains($searchText, ' 401')
            || str_contains($searchText, ' 403')
            || str_contains($searchText, ' 422')
            || str_contains($searchText, ' 500')
        ) {
            $status = 'error';
        }

        return [
            'type' => $type,
            'status' => $status,
        ];
    }

    /**
     * Send a password reset email to a user (Admin triggered)
     */
    public function sendPasswordResetEmail(\Illuminate\Http\Request $request, string $user): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::resolvePublicIdOrFail($user);
            $token = \Illuminate\Support\Str::random(60);

            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'email' => $user->email,
                    'token' => \Illuminate\Support\Facades\Hash::make($token),
                    'created_at' => now()
                ]
            );

            $resetUrl = config('app.frontend_url') . '/reinitialiser-mot-de-passe?token=' . $token . '&email=' . urlencode($user->email);

            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\ResetPassword($user, $resetUrl));

            return response()->json([
                'success' => true,
                'message' => 'E-mail de réinitialisation envoyé avec succès (valide 60 min).'
            ]);
        } catch (\Exception $e) {
            \Log::error("Error sending admin password reset: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'e-mail.'
            ], 500);
        }
    }
}

