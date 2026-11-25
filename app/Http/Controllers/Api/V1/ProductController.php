<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController
{
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(Product::query()->paginate(15));
    }
}
