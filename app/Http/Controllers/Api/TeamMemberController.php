<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeamMemberController extends Controller
{
    /**
     * Display a listing of the resource (Admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = TeamMember::with('user')->ordered();

        // Filtres
        if ($request->has('department')) {
            $query->where('department', $request->department);
        }
        
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%");
            });
        }

        $members = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * Store a newly created team member
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'position' => 'required|string|max:150',
            'department' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'bio' => 'nullable|string|max:1000',
            'social_links' => 'nullable|array',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'show_on_website' => 'boolean',
            'user_id' => 'nullable|exists:users,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Upload photo si présente
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $filename = 'team/' . Str::uuid() . '.' . $photo->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($photo));
            $validated['photo'] = $filename;
        }

        $member = TeamMember::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Membre ajouté avec succès',
            'data' => $member,
        ], 201);
    }

    /**
     * Display the specified team member
     */
    public function show(string $id): JsonResponse
    {
        $member = TeamMember::with('user')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $member,
        ]);
    }

    /**
     * Update the specified team member
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $member = TeamMember::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'position' => 'sometimes|required|string|max:150',
            'department' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'bio' => 'nullable|string|max:1000',
            'social_links' => 'nullable|array',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'show_on_website' => 'boolean',
            'user_id' => 'nullable|exists:users,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Upload nouvelle photo si présente
        if ($request->hasFile('photo')) {
            // Supprimer ancienne photo
            if ($member->photo && Storage::disk('public')->exists($member->photo)) {
                Storage::disk('public')->delete($member->photo);
            }
            
            $photo = $request->file('photo');
            $filename = 'team/' . Str::uuid() . '.' . $photo->getClientOriginalExtension();
            Storage::disk('public')->put($filename, file_get_contents($photo));
            $validated['photo'] = $filename;
        }

        $member->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Membre mis à jour avec succès',
            'data' => $member->fresh(),
        ]);
    }

    /**
     * Remove the specified team member
     */
    public function destroy(string $id): JsonResponse
    {
        $member = TeamMember::findOrFail($id);

        // Supprimer la photo
        if ($member->photo && Storage::disk('public')->exists($member->photo)) {
            Storage::disk('public')->delete($member->photo);
        }

        $member->delete();

        return response()->json([
            'success' => true,
            'message' => 'Membre supprimé avec succès',
        ]);
    }

    /**
     * Update display order for multiple members
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:team_members,id',
            'orders.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->orders as $item) {
            TeamMember::where('id', $item['id'])->update(['display_order' => $item['order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ordre mis à jour avec succès',
        ]);
    }

    /**
     * Toggle member visibility on website
     */
    public function toggleVisibility(string $id): JsonResponse
    {
        $member = TeamMember::findOrFail($id);
        $member->update(['show_on_website' => !$member->show_on_website]);

        return response()->json([
            'success' => true,
            'message' => $member->show_on_website ? 'Membre visible sur le site' : 'Membre masqué du site',
            'data' => $member,
        ]);
    }

    /**
     * Public endpoint - Get team for website
     */
    public function publicTeam(): JsonResponse
    {
        $members = TeamMember::forWebsite()->get();

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }
}
