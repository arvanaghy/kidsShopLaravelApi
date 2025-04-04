<?php

use App\Http\Controllers\CheckOutController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\OrderController;
use App\Http\Controllers\V1\InvoiceController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\CustomerController;
use App\Http\Controllers\V1\GreneralController;
use App\Http\Controllers\V1\WebPaymentController;

use App\Http\Controllers\V2\InnerPages\HomeController;
use App\Http\Controllers\V2\InnerPages\SubCategories;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {

    Route::get('/reset-images', [ProductController::class, 'resetImages']);
    Route::get('/online', [GreneralController::class, 'online']);
    Route::get('/faq', [GreneralController::class, 'faq']);
    Route::post('/insert-faq', [GreneralController::class, 'insert_faq']);
    Route::get('/about-us', [GreneralController::class, 'about_us']);
    Route::post('/login', [CustomerController::class, 'login']);
    Route::post('/register', [CustomerController::class, 'register']);
    Route::post('/verify-sms', [CustomerController::class, 'verify_sms']);
    Route::post('/resend-sms', [CustomerController::class, 'resend_sms']);
    Route::post('/verify-token', [CustomerController::class, 'verify_token']);
    Route::get('/check-online-payment-available', [GreneralController::class, 'checkOnlinePaymentAvailable']);
    Route::get('/checkout-with-order', [WebPaymentController::class, 'checkoutWithOrder']);
    Route::get('/checkout-without-order', [WebPaymentController::class, 'checkoutWithoutOrder']);
    Route::get('/checkout-with-order-mobile', [WebPaymentController::class, 'checkoutWithOrderMobile']);
    Route::get('/checkout-without-order-mobile', [WebPaymentController::class, 'checkoutWithoutOrderMobile']);
    Route::get('/list-categories', [CategoryController::class, 'list_categories']);
    Route::get('/offerd-products', [ProductController::class, 'offerd_products']);
    Route::get('/list-latest-products', [ProductController::class, 'list_latest_products']);
    Route::get('/show-product/{Code}', [ProductController::class, 'show_product']);
    Route::post('/contact-us', [GreneralController::class, 'contact_us']);
    Route::get('/list-subcategories/{Code}', [CategoryController::class, 'list_subcategories']);
    Route::get('/search-category-by-code/{Code}', [CategoryController::class, 'search_category_by_code']);
    Route::get('/search-subcategory-by-code/{Code}', [CategoryController::class, 'search_subcategory_by_code']);
    Route::get('/list-subcategory-products/{Code}', [ProductController::class, 'list_subcategory_products']);
    // Route::get('/search/{SearchPhrase}', [SearchController::class, 'search']);
    Route::get('/search-categories/{Search}', [CategoryController::class, 'search_categories']);
    Route::get('/search-subcategories/{Search}', [CategoryController::class, 'search_subcategories']);
    Route::get('/list-category-products/{Code}', [ProductController::class, 'list_category_products']);
    Route::get('/list-products', [ProductController::class, 'list_products']);
    Route::get('/list-products-for-website/{sortType}', [ProductController::class, 'list_products_for_website']);
    Route::get('/list-subcategory-products-for-website/{subcategoryCode}/{sortType}', [ProductController::class, 'list__subcategory_products_for_website']);
    Route::get('/list-subcategory-products-for-website-with-PCode/{ProducteCode}/{sortType}', [ProductController::class, 'list__subcategory_products_for_website_with_PCode']);
    Route::get('/list-category-products-for-website/{categoryCode}/{sortType}', [ProductController::class, 'list__category_products_for_website']);
    Route::get('/search/{SearchPhrase}', [ProductController::class, 'search_products']);
    Route::get('/banners', [GreneralController::class, 'fetchBanners']);
    Route::get('/list-transfer-services', [GreneralController::class, 'list_transfer_services']);
    Route::get('/best-seller', [ProductController::class, 'bestSeller']);
    Route::get('/same-price/{Code}', [ProductController::class, 'samePrice']);
    Route::get('/customer-category/{Code}', [CustomerController::class, 'customerCategory']);

    Route::get('/zarinpal-payment-callback', [CheckOutController::class, 'zarinpal_payment_callback'])->name('zarinpal-payment-callback');
    Route::get('/zarinpal-payment-callback-mobile', [CheckOutController::class, 'zarinpal_payment_callback_mobile'])->name('zarinpal-payment-callback-mobile');

    Route::middleware('customerConfirm')->group(function () {
        Route::post('/log-out', [CustomerController::class, 'logOut']);
        Route::get('/list-past-invoice', [InvoiceController::class, 'list_past_invoices']);
        Route::get('/list-past-orders', [InvoiceController::class, 'list_past_orders']);
        Route::get('/list-past-orders-products/{order}', [InvoiceController::class, 'list_past_orders_products']);
        Route::get('/list-unverified-orders', [InvoiceController::class, 'list_unverified_orders']);
        Route::get('/list-unverified-orders-products/{order}', [InvoiceController::class, 'list_unverified_orders_products']);
        Route::get('/account-balance', [InvoiceController::class, 'account_balance']);
        Route::post('/edit-user-info', [GreneralController::class, 'edit_user_info']);
        Route::post('/submit-order', [OrderController::class, 'submit_order']);
    });
});


Route::prefix('v2')->group(function () {
    Route::get('/top-menu', [HomeController::class, 'topMenu']);
    Route::get('/home-page', [HomeController::class, 'homePage']);
    Route::get('/list-subcategories/{Code}', [SubCategories::class, 'index']);

});

