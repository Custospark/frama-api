<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the plain summary array from BranchShareService. The array is already
 * presentation-ready, so `toArray` returns it verbatim (minus the string key).
 */
class BranchShareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
