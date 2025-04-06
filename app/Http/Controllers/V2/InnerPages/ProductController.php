<?php

namespace App\Http\Controllers\V2\InnerPages;

use App\Http\Controllers\Controller;
use App\Models\ProductImagesModel;
use App\Models\ProductModel;
use Exception;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function showProduct(Request $request, $Code)
    {
        try {
            $product = ProductModel::with(['productSizeColor'])->where('Code', $Code)->first();

            

            if (!$product) {
                return response()->json(['error' => 'محصول یافت نشد'], 404);
            }





            return response()->json($product);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
