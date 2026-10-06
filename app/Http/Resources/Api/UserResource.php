<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'photo' => $this->photo,
            'gender' => $this->gender,
            'must_change_password' => (bool) $this->must_change_password,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'institution' => $this->institution ? [
                'id' => $this->institution->id,
                'slug' => $this->institution->slug,
                'name' => $this->institution->name,
                'status' => $this->institution->status,
                'is_read_only' => $this->institution->isReadOnly(),
            ] : null,
            'roles' => $this->when($this->relationLoaded('roles'), function () {
                return $this->roles->pluck('name');
            }),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}