<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Border;
use App\Models\BorderCategory;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use ZipArchive;

class BorderController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:borders.view']);
    }

    public function index(): Response
    {
        $borders = Border::with('category')
            ->orderBy('name')
            ->paginate(20)
            ->through(function ($border) {
                return [
                    'id' => $border->id,
                    'slug' => $border->slug,
                    'name' => $border->name,
                    'aspect_ratio' => $border->aspect_ratio,
                    'preview_url' => asset('storage/' . $border->preview_path),
                    'category' => $border->category ? [
                        'id' => $border->category->id,
                        'name' => $border->category->name,
                    ] : null,
                    'is_active' => $border->is_active,
                    'created_at' => $border->created_at->format('M d, Y'),
                ];
            });

        $categories = BorderCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Borders/Index', [
            'borders' => $borders,
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('borders.create');
        
        $categories = BorderCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Borders/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('borders.create');

        $validator = Validator::make($request->all(), [
            'border_pack' => 'required|file|mimes:zip|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $this->processBorderPack($request->file('border_pack'));
            
            return redirect()->route('admin.borders.index')
                ->with('success', 'Border pack uploaded successfully!');
                
        } catch (\Exception $e) {
            return back()->withErrors(['border_pack' => $e->getMessage()])->withInput();
        }
    }

    public function show(Border $border): Response
    {
        $border->load('category');
        
        return Inertia::render('Admin/Borders/Show', [
            'border' => [
                'id' => $border->id,
                'slug' => $border->slug,
                'name' => $border->name,
                'aspect_ratio' => $border->aspect_ratio,
                'preview_url' => asset('storage/' . $border->preview_path),
                'file_url' => asset('storage/' . $border->file_path),
                'manifest' => $border->manifest,
                'category' => $border->category ? [
                    'id' => $border->category->id,
                    'name' => $border->category->name,
                ] : null,
                'is_active' => $border->is_active,
                'created_at' => $border->created_at->format('M d, Y H:i'),
                'updated_at' => $border->updated_at->format('M d, Y H:i'),
            ],
        ]);
    }

    public function update(Request $request, Border $border): RedirectResponse
    {
        $this->authorize('borders.update');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:border_categories,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $border->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'category_id' => $request->category_id,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.borders.index')
            ->with('success', 'Border updated successfully!');
    }

    public function destroy(Border $border): RedirectResponse
    {
        $this->authorize('borders.delete');

        // Delete files
        Storage::disk('public')->delete([
            $border->preview_path,
            $border->file_path,
        ]);

        $border->delete();

        return redirect()->route('admin.borders.index')
            ->with('success', 'Border deleted successfully!');
    }

    public function toggleActive(Border $border): RedirectResponse
    {
        $this->authorize('borders.update');

        $border->update(['is_active' => !$border->is_active]);

        return back()->with('success', 
            $border->is_active ? 'Border activated!' : 'Border deactivated!'
        );
    }

    private function processBorderPack($zipFile): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipFile->path());

        if ($result !== TRUE) {
            throw new \Exception('Failed to open ZIP file');
        }

        // Extract to temporary directory
        $tempDir = storage_path('app/temp/' . Str::random(10));
        $zip->extractTo($tempDir);
        $zip->close();

        try {
            // Look for manifest.json
            $manifestPath = $tempDir . '/manifest.json';
            if (!file_exists($manifestPath)) {
                throw new \Exception('manifest.json not found in ZIP file');
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (!$manifest) {
                throw new \Exception('Invalid manifest.json format');
            }

            // Validate manifest
            $this->validateManifest($manifest);

            // Check for required files
            $previewPath = $tempDir . '/preview.png';
            $framePath = $tempDir . '/frame.png';

            if (!file_exists($previewPath)) {
                throw new \Exception('preview.png not found in ZIP file');
            }

            if (!file_exists($framePath)) {
                throw new \Exception('frame.png not found in ZIP file');
            }

            // Get or create category
            $category = BorderCategory::firstOrCreate(
                ['slug' => Str::slug($manifest['category'])],
                ['name' => $manifest['category']]
            );

            // Check if border already exists
            if (Border::where('slug', $manifest['slug'])->exists()) {
                throw new \Exception('Border with this slug already exists: ' . $manifest['slug']);
            }

            // Store files
            $borderDir = 'borders/' . $manifest['slug'];
            $previewStorePath = $borderDir . '/preview.png';
            $frameStorePath = $borderDir . '/frame.png';

            Storage::disk('public')->put($previewStorePath, file_get_contents($previewPath));
            Storage::disk('public')->put($frameStorePath, file_get_contents($framePath));

            // Create border record
            Border::create([
                'slug' => $manifest['slug'],
                'name' => $manifest['name'],
                'category_id' => $category->id,
                'aspect_ratio' => $manifest['aspect_ratio'],
                'preview_path' => $previewStorePath,
                'file_path' => $frameStorePath,
                'manifest' => $manifest,
                'is_active' => true,
            ]);

        } finally {
            // Clean up temporary directory
            $this->deleteDirectory($tempDir);
        }
    }

    private function validateManifest(array $manifest): void
    {
        $required = ['name', 'slug', 'category', 'aspect_ratio', 'safe_zone'];
        
        foreach ($required as $field) {
            if (!isset($manifest[$field])) {
                throw new \Exception("Missing required field in manifest: {$field}");
            }
        }

        // Validate aspect ratio format
        if (!preg_match('/^\d+:\d+$/', $manifest['aspect_ratio'])) {
            throw new \Exception('Invalid aspect_ratio format. Expected format: "width:height" (e.g., "4:3")');
        }

        // Validate safe zone
        $safeZone = $manifest['safe_zone'];
        $requiredSafeZone = ['x', 'y', 'width', 'height'];
        
        foreach ($requiredSafeZone as $field) {
            if (!isset($safeZone[$field]) || !is_numeric($safeZone[$field])) {
                throw new \Exception("Invalid safe_zone.{$field} in manifest");
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}
