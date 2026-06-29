<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait DataTablesResponse
{
    protected function dataTablesResponse(Request $request, LengthAwarePaginator $paginator, array $response): array
    {
        return array_merge([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $paginator->total(),
            'recordsFiltered' => $paginator->total(),
        ], $response);
    }
}