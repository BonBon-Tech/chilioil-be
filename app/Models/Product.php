<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'store_id',
        'product_category_id',
        'selling_type',
        'image_path',
        'price',
        'status',
    ];

    protected $appends = ['image_url'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? url(Storage::url($this->image_path)) : null;
    }
}
