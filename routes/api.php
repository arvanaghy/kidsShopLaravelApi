<?php

use App\Http\Controllers\DevelopController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\SubCategoriesController;
use App\Http\Controllers\V1\OrderAndPaymentController;


use App\Http\Controllers\V2\InnerPages\HomeController as WebHomeController;
use App\Http\Controllers\V2\InnerPages\SubCategoriesController as WebSubCategoriesController;
use App\Http\Controllers\V2\InnerPages\CategoriesController as WebCategoriesController;
use App\Http\Controllers\V2\EnquireController;
use App\Http\Controllers\V2\CustomerController as WebCustomerController;
use App\Http\Controllers\V2\GeneralController as WebGeneralController;
use App\Http\Controllers\V2\InnerPages\ProductController as WebProductController;
use App\Http\Controllers\V2\OrderAndPaymentController as WebOrderAndPaymentController;
use App\Http\Controllers\V2\InnerPages\CheckOutController as WebCheckOutController;

use App\Http\Controllers\V2\Profile\InvoiceController as WebInvoiceController;



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


Route::prefix('develop')->group(function () {
    // Route::get('/reset-images', [DevelopController::class, 'resetCChangePic']);
    // Route::get('/test-redis', [DevelopController::class, 'testRedis']);
    // Route::get('/test-order', [DevelopController::class, 'testOrder']);
    // Route::get('/sms-result', [DevelopController::class, 'smsResult']);
    Route::get('/send-email', [DevelopController::class, 'sendEmail']);
});

Route::prefix('general')->group(function () {
    Route::get('/online', [WebGeneralController::class, 'isServerOnline']);
    Route::prefix('company')->group(function () {
        Route::get('/company-info', [WebGeneralController::class, 'companyInfo']);
        Route::get('/faq', [WebGeneralController::class, 'faq']);
        Route::get('/about-us', [WebGeneralController::class, 'aboutUs']);
    });
    Route::prefix('ui-ux')->group(function () {
        Route::get('/top-menu', [WebGeneralController::class, 'topMenu']);
    });
    Route::prefix('financial')->group(function () {
        Route::get('/check-online-payment-available', [WebGeneralController::class, 'checkOnlinePaymentAvailable']);
        Route::get('/list-transfer-services', [WebGeneralController::class, 'listTransferServices']);
        Route::get('/currency-unit', [WebGeneralController::class, 'currencyUnit']);
    });
    Route::post('/contact-us', [EnquireController::class, 'contact_us']);
    Route::get('/home-page', [WebHomeController::class, 'homePage']);
    Route::get('/list-categories', [WebCategoriesController::class, 'listCategories']);

    Route::prefix('customer-auth')->group(function () {
        Route::post('/login', [WebCustomerController::class, 'login']);
        Route::post('/register', [WebCustomerController::class, 'register']);
        Route::post('/verify-sms', [WebCustomerController::class, 'verifySms']);
        Route::post('/resend-sms', [WebCustomerController::class, 'resendSms']);
        Route::post('/verify-token', [WebCustomerController::class, 'verifyToken']);
        Route::middleware('customerConfirm')->group(function () {
            Route::post('/log-out', [WebCustomerController::class, 'logOut']);
            Route::post('/update-user-address', [WebCustomerController::class, 'updateUserAddress']);
            Route::post('/edit-user-info', [WebCustomerController::class, 'editUserInfo']);
        });
    });

    Route::prefix('profile')->group(function () {
        Route::middleware('customerConfirm')->group(function () {
            Route::get('/list-past-invoice', [WebInvoiceController::class, 'listPastInvoices']);
            Route::get('/list-past-orders', [WebInvoiceController::class, 'listPastOrders']);
            Route::get('/list-past-orders-products/{order}', [WebInvoiceController::class, 'listPastOrdersProducts']);
            Route::get('/list-unverified-orders', [WebInvoiceController::class, 'listUnverifiedOrders']);
            Route::get('/list-unverified-orders-products/{order}', [WebInvoiceController::class, 'listUnverifiedOrdersProducts']);
            Route::get('/account-balance', [WebInvoiceController::class, 'accountBalance']);
        });
    });

    Route::get('/payment-callback', [WebCheckOutController::class, 'paymentCallback'])->name('payment-callback');
    Route::get('/payment-callback-mobile', [WebCheckOutController::class, 'paymentCallbackMobile'])->name('payment-callback-mobile');
});

Route::prefix('v1')->group(function () {

    Route::prefix('categories-and-subcategories')->group(function () {
        Route::get('/list-category-subcategories-and-products/{Code}', [SubCategoriesController::class, 'listCategorySubcategoriesAndProducts']);
        Route::get('/list-subcategory-products/{Code}', [SubCategoriesController::class, 'listSubcategoryProducts']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/list-all-products/', [ProductController::class, 'listAllProducts']);
        Route::get('/list-all-offers/', [ProductController::class, 'listOfferedProducts']);
        Route::get('/list-best-seller/', [ProductController::class, 'listBestSellingProducts']);
        Route::get('/show-product/{Code}', [ProductController::class, 'showProduct']);
    });

    Route::middleware('customerConfirm')->group(function () {
        Route::post('/process-order-and-payment', [OrderAndPaymentController::class, 'process']);
    });

    Route::get('/customer-category/{Code}', [WebCustomerController::class, 'customerCategory']);
});


Route::prefix('v2')->group(function () {

    Route::prefix('categories-and-subcategories')->group(function () {
        Route::get('/list-category-subcategories-and-products/{Code}', [WebSubCategoriesController::class, 'listCategorySubcategoriesAndProducts']);
        Route::get('/list-subcategory-products/{Code}', [WebSubCategoriesController::class, 'listSubcategoryProducts']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/list-all-products/', [WebProductController::class, 'listAllProducts']);
        Route::get('/list-all-offers/', [WebProductController::class, 'listOfferedProducts']);
        Route::get('/list-best-seller/', [WebProductController::class, 'listBestSellingProducts']);
        Route::get('/show-product/{Code}', [WebProductController::class, 'showProduct']);
    });

    Route::get('/payment-callback', [WebCheckOutController::class, 'paymentCallback'])->name('payment-callback');
    Route::get('/payment-callback-mobile', [WebCheckOutController::class, 'paymentCallbackMobile'])->name('payment-callback-mobile');

    Route::middleware('customerConfirm')->group(function () {
        Route::post('/process-order-and-payment', [WebOrderAndPaymentController::class, 'processOrderAndPayment']);
    });

    Route::middleware('adminConfirm')->group(function () {
        Route::prefix('products')->group(function () {
            Route::post('/upload-product-images/{Code}', [WebProductController::class, 'uploadProductImages']);
            Route::post('/update-product-comment/{Code}', [WebProductController::class, 'updateProductComment']);
            Route::delete('/delete-product-images/{Code}', [WebProductController::class, 'deleteProductImages']);
        });
        Route::prefix('orders')->group(function () {
            Route::get('/', [WebInvoiceController::class, 'listAllOrders']);
            Route::get('/{id}', [WebInvoiceController::class, 'showOrder']);
        });
    });
});
