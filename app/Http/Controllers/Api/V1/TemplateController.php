<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Template\StoreTemplateRequest;
use App\Http\Requests\Template\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function __construct(private StorageService $storage) {}

    /**
     * GET /api/v1/templates
     *
     * Returns templates the authenticated user can use:
     * their own + all public + system templates.
     *
     * Query params:
     *   filter=own|public|system
     *   per_page=N (default 15, max 50)
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('viewAny', Template::class)) {
            return $this->forbidden();
        }

        $perPage = min((int) ($request->per_page ?? 15), 50);

        $templates = Template::availableFor($request->user()->id)
            ->when($request->filter === 'own',    fn ($q) => $q->ownedBy($request->user()->id))
            ->when($request->filter === 'public', fn ($q) => $q->public())
            ->when($request->filter === 'system', fn ($q) => $q->system())
            ->latest()
            ->paginate($perPage);

        return $this->success(TemplateResource::collection($templates)->response()->getData(true));
    }

    /**
     * POST /api/v1/templates
     *
     * Accepts multipart/form-data when uploading a preview image,
     * or application/json when no image is provided.
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        if (! $request->user()->can('create', Template::class)) {
            return $this->forbidden();
        }

        $data            = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['config'] ??= [];

        if ($request->hasFile('preview')) {
            $path              = $this->storage->uploadPreview($request->file('preview'));
            $data['preview_url'] = $this->storage->url($path);
        }

        unset($data['preview']);

        $template = Template::create($data);

        return $this->created(new TemplateResource($template), 'Template created successfully');
    }

    /**
     * GET /api/v1/templates/{template}
     *
     * Accessible if the template is owned by the user, public, or system.
     */
    public function show(Request $request, Template $template): JsonResponse
    {
        if (! $request->user()->can('view', $template)) {
            return $this->forbidden();
        }

        return $this->success(new TemplateResource($template));
    }

    /**
     * PUT /api/v1/templates/{template}
     *
     * Only the owner can update. config is replaced wholesale when provided.
     * Send a new preview file to replace the current preview image.
     */
    public function update(UpdateTemplateRequest $request, Template $template): JsonResponse
    {
        if (! $request->user()->can('update', $template)) {
            return $this->forbidden();
        }

        $data = $request->validated();

        if ($request->hasFile('preview')) {
            $path              = $this->storage->uploadPreview($request->file('preview'));
            $data['preview_url'] = $this->storage->url($path);
        }

        unset($data['preview']);

        $template->update($data);

        return $this->success(new TemplateResource($template->fresh()), 'Template updated successfully');
    }

    /**
     * DELETE /api/v1/templates/{template}
     *
     * Only the owner can delete. System templates (user_id null) are protected.
     */
    public function destroy(Request $request, Template $template): JsonResponse
    {
        if (! $request->user()->can('delete', $template)) {
            return $this->forbidden();
        }

        $template->delete();

        return $this->success(null, 'Template deleted successfully');
    }
}
