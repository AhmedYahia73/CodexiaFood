<?php

namespace App\Http\Resources;

use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasPermissionsLoaded = $this->relationLoaded('permissions');
        $grouped = null;

        if ($hasPermissionsLoaded) {
            /** @var PermissionService $permissionService */
            $permissionService = app(PermissionService::class);
            $grouped = $permissionService->groupPermissions($this->permissions);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'permissions_count' => $this->when($hasPermissionsLoaded, fn () => $this->permissions->count()),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'grouped_permissions' => $this->when($hasPermissionsLoaded, $grouped),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
