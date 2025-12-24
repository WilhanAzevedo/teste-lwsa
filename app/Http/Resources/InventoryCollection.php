<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InventoryCollection extends ResourceCollection
{

    public $collects = InventoryResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function paginationInformation($request, $paginated, $default)
    {
       
        $default['meta'] = [
            'current_page' => $default['meta']['current_page'],
            'per_page'     => $default['meta']['per_page'],
            'total'        => $default['meta']['total'],
            'last_page'    => $default['meta']['last_page'],
        ];

        $meta['meta'] = $default['meta'];

        return $meta;
    }
}
