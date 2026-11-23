<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ProfileMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $member = [
            'username' => $this->username,
            'name' => $this->name,
        ];

        // Set by the controller for the owner's own followers list only.
        if (array_key_exists('is_new', $this->resource->getAttributes())) {
            $member['is_new'] = (bool) $this->resource->getAttribute('is_new');
        }

        return $member;
    }
}
