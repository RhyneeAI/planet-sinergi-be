<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'period'                   => $this['period'],
            'total_products'           => $this['total_products'],
            'total_marketing'          => $this['total_marketing'],
            'total_customers'          => $this['total_customers'],
            'total_sales_nominal'      => $this['total_sales_nominal'],
            'total_sales_transactions' => $this['total_sales_transactions'],
        ];
    }
}
