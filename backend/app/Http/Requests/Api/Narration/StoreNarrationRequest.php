<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Narration;

use App\Http\Requests\Narration\StoreNarrationRequest as WebStoreNarrationRequest;
use App\Models\Reference\ActivityType;

/** The web's narration rules, read the way the API's controller needs them. */
class StoreNarrationRequest extends WebStoreNarrationRequest
{
    public function activityType(): ActivityType
    {
        return ActivityType::findOrFail($this->validated('activity_type_id'));
    }
}
