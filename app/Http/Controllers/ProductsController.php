<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        // create a search for pproduct
        $builder = Product::query()->where('on_sale', true);
        // find the parameter and $search = parameter
        // search by parameter
        if ($search = $request->input('search', '')) {
            $like = '%'.$search.'%';
            // search in product name, description SKU name and SKU description
            $builder->where(function ($query) use ($like) {
                $query->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('skus', function ($query) use ($like) {
                        $query->where('title', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    });
            });
        }

        // find parameter of order
        // order decide the showing order
        if ($order = $request->input('order', '')) {
            // _asc or _desc
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                // the arrary begin with price, sold_count or rating, it is valid
                if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
                    // 
                    $builder->orderBy($m[1], $m[2]);
                }
            }
        }

        $products = $builder->paginate(16);

        return view('products.index', [
            'products' => $products,
            'filters'  => [
                'search' => $search,
                'order'  => $order,
            ],
        ]);
    }

    public function show(Product $product, Request $request)
    {
        // if product is not sold
        if (!$product->on_sale) {
            throw new InvalidRequestException('Product is not in selling');
        }

        $favored = false;
        // if not login, return null. if login, return user
        if($user = $request->user()) {
            // find the product by id and search from loved products
            // boolval() convert value type to boolean
            $favored = boolval($user->favoriteProducts()->find($product->id));
        }

        $reviews = OrderItem::query()
            ->with(['order.user', 'productSku']) // preload
            ->where('product_id', $product->id)
            ->whereNotNull('reviewed_at') // filter the reviewed products
            ->orderBy('reviewed_at', 'desc') // order by time
            ->limit(10) // take 10 of them
            ->get();

        return view('products.show', [
            'product' => $product,
            'favored' => $favored,
            'reviews' => $reviews
        ]);
    }

    public function favor(Product $product, Request $request)
    {
        $user = $request->user();
        if ($user->favoriteProducts()->find($product->id)) {
            return [];
        }

        $user->favoriteProducts()->attach($product);

        return [];
    }

    public function disfavor(Product $product, Request $request)
    {
        $user = $request->user();
        $user->favoriteProducts()->detach($product);

        return [];
    }

    public function favorites(Request $request)
    {
        $products = $request->user()->favoriteProducts()->paginate(16);

        return view('products.favorites', ['products' => $products]);
    }
}
