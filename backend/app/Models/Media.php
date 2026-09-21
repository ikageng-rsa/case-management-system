<?php

namespace App\Models;

use App\Concerns\RecordsActivity;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    use RecordsActivity;

    public function auditLabel(): string
    {
        return 'document '.$this->file_name;
    }

    protected function auditLogName(): string
    {
        return 'document';
    }
}
