<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = ProjectRequest::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->input('search')).'%';

                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->latest()
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $requests]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $projectRequest = ProjectRequest::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(ProjectRequest::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $projectRequest->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan diperbarui.',
            'data' => $projectRequest->fresh(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        ProjectRequest::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permintaan dihapus.',
        ]);
    }
}
