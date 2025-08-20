<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductSalesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'product_id' => $this->product_id,
            'product_name' => $this->product ? $this->product->name : null,
            'product' => $this->product,
            'total_qty' => (int) $this->total_qty,
            'total_sales' => (float) $this->total_sales,
        ];
    }
}

