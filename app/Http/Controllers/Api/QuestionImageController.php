<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InstitutionContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestionImageController extends Controller
{
    private InstitutionContext $context;

    public function __construct(InstitutionContext $context)
    {
        $this->context = $context;
    }

    /**
     * Upload an image for question or option content (TinyMCE integration).
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048', // max 2MB
        ]);

        $institution = $this->context->getInstitution();
        if (!$institution) {
            return response()->json([
                'success' => false,
                'message' => 'Institution context is required'
            ], 403);
        }

        $file = $request->file('file');
        $fileSize = $file->getSize();

        // Enforce storage quota
        if ($institution->hasReachedStorageQuota($fileSize)) {
            return response()->json([
                'success' => false,
                'message' => 'Kapasitas penyimpanan lembaga telah melebihi batas kuota.'
            ], 422);
        }

        $ext = $file->getClientOriginalExtension() ?: 'png';
        $filename = Str::uuid() . '.' . $ext;
        $path = "institutions/{$institution->id}/questions";

        // Store to public disk
        $storedPath = $file->storeAs($path, $filename, 'public');

        // Increment tenant storage tracking
        $institution->recordStorageAdded($fileSize);

        $url = asset('storage/' . $storedPath);

        return response()->json([
            'success' => true,
            'location' => $url,
            'url' => $url,
            'size' => $fileSize
        ]);
    }
}
