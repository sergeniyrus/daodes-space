<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\Auth;

class OrganizationActivityLogger
{
    public function record(
        Organization $organization,
        string $action,
        string $description,
        array $beforeValues = [],
        array $afterValues = [],
        ?string $subjectType = null,
        ?int $subjectId = null
    ): void {
        OrganizationActivityLog::query()->create([
            'organization_id' => $organization->id,
            'actor_id' => Auth::id() ?? Auth::guard('employee')->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'before_values' => $beforeValues !== [] ? $beforeValues : null,
            'after_values' => $afterValues !== [] ? $afterValues : null,
        ]);
    }
}
