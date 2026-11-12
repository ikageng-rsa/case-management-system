<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/*
 * What the API answers with. The server decides what comes with a resource: the
 * relations the controller has loaded are included, and the "include" and
 * "fields" a request asks for are not followed, so nothing is loaded one row at
 * a time and nothing leaves that the endpoint did not mean to give. Lookups
 * wrap enums, which have no relations, so only models include loaded ones.
 */
abstract class ApiResource extends JsonApiResource
{
    public function __construct($resource)
    {
        parent::__construct($resource);

        $this->ignoreFieldsAndIncludesInQueryString();
        if ($resource instanceof Model) {
            $this->includePreviouslyLoadedRelationships();
        }
    }
}
