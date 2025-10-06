<?php

use App\Http\Controllers\CheckOutController;
use App\Http\Controllers\DevelopController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\OrderController;
use App\Http\Controllers\V1\InvoiceController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\CustomerController;
use App\Http\Controllers\V1\GeneralController;
use App\Http\Controllers\V1\WebPaymentController;

use App\Http\Controllers\V2\InnerPages\HomeController as WebHomeController;
use App\Http\Controllers\V2\InnerPages\SubCategories as WebSubCategories;
use App\Http\Controllers\V2\InnerPages\CategoriesController as WebCategoriesController;
use App\Http\Controllers\V2\InnerPages\GeneralController as WebGeneralController;
use App\Http\Controllers\V2\InnerPages\ProductController as WebProductController;
use App\Http\Controllers\V2\OrderAndPaymentController as WebOrderAndPaymentController;



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
    Route::get('/online', [GeneralController::class, 'online']);
    Route::get('/faq', [GeneralController::class, 'faq']);

    Route::get('/about-us', [GeneralController::class, 'about_us']);
    Route::post('/login', [CustomerController::class, 'login']);
    Route::post('/register', [CustomerController::class, 'register']);
    Route::post('/verify-sms', [CustomerController::class, 'verify_sms']);
    Route::post('/resend-sms', [CustomerController::class, 'resend_sms']);
    Route::post('/verify-token', [CustomerController::class, 'verify_token']);
    Route::get('/check-online-payment-available', [GeneralController::class, 'checkOnlinePaymentAvailable']);
    Route::get('/checkout-with-order', [WebPaymentController::class, 'checkoutWithOrder']);
    Route::get('/checkout-without-order', [WebPaymentController::class, 'checkoutWithoutOrder']);
    Route::get('/checkout-with-order-mobile', [WebPaymentController::class, 'checkoutWithOrderMobile']);
    Route::get('/checkout-without-order-mobile', [WebPaymentController::class, 'checkoutWithoutOrderMobile']);
    Route::get('/list-categories', [CategoryController::class, 'list_categories']);
    Route::get('/offered-products', [ProductController::class, 'offered_products']);
    Route::get('/list-latest-products', [ProductController::class, 'list_latest_products']);
    Route::get('/show-product/{Code}', [ProductController::class, 'show_product']);
    Route::post('/contact-us', [GeneralController::class, 'contact_us']);
    Route::get('/list-subcategories/{Code}', [CategoryController::class, 'list_subcategories']);
    Route::get('/search-category-by-code/{Code}', [CategoryController::class, 'search_category_by_code']);
    Route::get('/search-subcategory-by-code/{Code}', [CategoryController::class, 'search_subcategory_by_code']);
    Route::get('/list-subcategory-products/{Code}', [ProductController::class, 'list_subcategory_products']);

    Route::get('/search-categories/{Search}', [CategoryController::class, 'search_categories']);
    Route::get('/search-subcategories/{Search}', [CategoryController::class, 'search_subcategories']);
    Route::get('/list-category-products/{Code}', [ProductController::class, 'list_category_products']);
    Route::get('/list-products', [ProductController::class, 'list_products']);
    Route::get('/list-products-for-website/{sortType}', [ProductController::class, 'list_products_for_website']);
    Route::get('/list-subcategory-products-for-website/{subcategoryCode}/{sortType}', [ProductController::class, 'list__subcategory_products_for_website']);
    Route::get('/list-subcategory-products-for-website-with-PCode/{ProductCode}/{sortType}', [ProductController::class, 'list__subcategory_products_for_website_with_PCode']);
    Route::get('/list-category-products-for-website/{categoryCode}/{sortType}', [ProductController::class, 'list__category_products_for_website']);
    Route::get('/search/{SearchPhrase}', [ProductController::class, 'search_products']);
    Route::get('/banners', [GeneralController::class, 'fetchBanners']);
    Route::get('/list-transfer-services', [GeneralController::class, 'list_transfer_services']);
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
        Route::post('/edit-user-info', [GeneralController::class, 'edit_user_info']);
        Route::post('/submit-order', [OrderController::class, 'submit_order']);
    });
});


Route::prefix('v2')->group(function () {
    Route::get('/company-info', [WebGeneralController::class, 'companyInfo']);
    Route::get('/currency-unit', [WebGeneralController::class, 'currencyUnit']);
    Route::get('/top-menu', [WebGeneralController::class, 'topMenu']);
    Route::get('/home-page', [WebHomeController::class, 'homePage']);
    Route::get('/reset-images', [DevelopController::class, 'resetCChangePic']);
    Route::get('/test-redis', [DevelopController::class, 'testRedis']);
    Route::get('/list-categories', [WebCategoriesController::class, 'listCategories']);
    Route::get('/list-subcategories/{Code}', [WebSubCategories::class, 'index']);
    Route::get('/list-subcategory-products/{Code}', [WebSubCategories::class, 'listSubcategoryProducts']);
    Route::get('/list-all-products/', [WebSubCategories::class, 'listAllProducts']);
    Route::get('/list-all-offers/', [WebSubCategories::class, 'listAllOffers']);
    Route::get('/list-best-seller/', [WebProductController::class, 'listBestSeller']);
    Route::get('/show-product/{Code}', [WebProductController::class, 'showProduct']);

    Route::middleware('customerConfirm')->group(function () {
        Route::post('/update-user-address', [CustomerController::class, 'updateUserAddress']);
        Route::post('/process-order-and-payment', [WebOrderAndPaymentController::class, 'process']);
    });
});
