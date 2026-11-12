<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\Organisation\CustomerController;
use App\Http\Controllers\Api\Organisation\DomainController;
use App\Http\Controllers\Api\Organisation\EventController;
use App\Http\Controllers\Api\Organisation\OrderController;
use App\Http\Controllers\Api\Organisation\OrganisationController;
use App\Http\Controllers\Api\Organisation\OrgStripeController;
use App\Http\Controllers\Api\Organisation\SiteBannerController;
use App\Http\Controllers\Api\Organisation\SiteCategoryController;
use App\Http\Controllers\Api\Organisation\SiteController;
use App\Http\Controllers\Api\Organisation\SiteNewsController;
use App\Http\Controllers\Api\Organisation\SiteSupportContactController;
use App\Http\Controllers\Api\Organisation\RolePermissionController;
use App\Http\Controllers\Api\Organisation\StaffMemberController;
use App\Http\Controllers\Api\Organisation\VenueController;
use App\Http\Controllers\Api\Platform\OrganisationController as PlatformOrganisationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Public\AuthController as PublicAuthController;
use App\Http\Controllers\Api\Public\BookingController as PublicBookingController;
use App\Http\Controllers\Api\Public\EventController as PublicEventController;
use App\Http\Controllers\Api\Public\MicrositeController as PublicMicrositeController;
use App\Http\Controllers\Api\Public\OrderController as PublicOrderController;
use App\Http\Controllers\Api\Public\ResolveHostnameController;
use App\Http\Controllers\Api\Public\TimezoneController as PublicTimezoneController;
use App\Http\Controllers\Api\Public\WishlistController as PublicWishlistController;
use App\Http\Controllers\Api\Webhooks\Stripe\StripeAccountWebhook;
use App\Http\Controllers\Api\Webhooks\Stripe\StripePaymentWebhook;
use App\Http\Controllers\Api\Webhooks\Vercel\VercelWebhook;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/php-config-check', function () {
    return response()->json([
        'php_version' => PHP_VERSION,
        'php_ini' => php_ini_loaded_file(),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
    ]);
});

Route::post('/organisation/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('organisation.login');

Route::post('/platform/login', [AuthController::class, 'platformLogin'])
    ->middleware('throttle:login')
    ->name('platform.login');

Route::post('/organiser/register', [AuthController::class, 'registerOrganiser'])
    ->name('organiser.register');

Route::post('/organiser/verify-email', [AuthController::class, 'verifyOrganiserEmail'])
    ->name('organiser.verify-email');

Route::post('/account-webhooks', StripeAccountWebhook::class)
    ->name('stripe.account-webhooks');

Route::post('/stripe/payment-webhooks', StripePaymentWebhook::class)
    ->name('stripe.payment-webhooks');

Route::post('/vercel/webhooks', VercelWebhook::class)
    ->name('vercel.webhooks');

// ################## Public Venue Option Lists ##################
Route::prefix('venue')->group(function () {
    Route::get('/types', [VenueController::class, 'getVenueTypeList']);
});

// ################## Public Customer Catalog (no auth) ##################
Route::prefix('public')->group(function () {
    Route::prefix('events')->group(function () {
        Route::get('/list', [PublicEventController::class, 'list']);
        Route::get('/details/{unique_id}', [PublicEventController::class, 'details']);
        Route::get('/tickets/{unique_id}', [PublicEventController::class, 'tickets']);
    });

    Route::get('/timezones', [PublicTimezoneController::class, 'list']);

    Route::get('/resolve-hostname', ResolveHostnameController::class);

    Route::get('/get-home-category-events', [PublicMicrositeController::class, 'getHomeCategoryEvents']);
    Route::get('/get-home-category-news', [PublicMicrositeController::class, 'getHomeCategoryNews']);
    Route::get('/get-support-contacts', [PublicMicrositeController::class, 'getSupportContacts']);

    Route::post('/send-login-otp', [PublicAuthController::class, 'sendLoginOtp'])
        ->middleware('throttle:login');
    Route::post('/verify-login-otp', [PublicAuthController::class, 'verifyLoginOtp'])
        ->middleware('throttle:login');
});

// ################## Public Event Option Lists ##################
Route::get('/event/categories', [EventController::class, 'getEventCategoryList']);

Route::middleware('jwt')->group(function () {
    Route::get('/user', function (Request $request) {
        return ApiResponse::success(data: UserResource::make($request->user()));
    })->name('api.user');

    Route::get('/about-me', [AuthController::class, 'aboutMe'])
        ->name('auth.about-me');

    Route::get('/profile/details', [ProfileController::class, 'details'])
        ->name('profile.details');

    Route::prefix('media')->group(function () {
        Route::post('/upload', [MediaController::class, 'upload'])->name('media.upload');
        Route::delete('/', [MediaController::class, 'delete'])->name('media.delete');
    });

    // ########### Public Auth Routes ##################
    Route::prefix('public')->group(function () {
        Route::post('/start-booking-ticket', [PublicBookingController::class, 'startBookingTicket']);
        Route::get('/order-status/{order_id}', [PublicOrderController::class, 'orderStatus']);
        Route::get('/my-orders-list', [PublicOrderController::class, 'myOrdersList']);
        Route::get('/get-wishlist', [PublicWishlistController::class, 'list']);
        Route::post('/move-to-wishlist', [PublicWishlistController::class, 'store']);
        Route::post('/remove-from-wishlist', [PublicWishlistController::class, 'remove']);
    });

    Route::get('/my-org/details', [OrganisationController::class, 'getOrganisationDetail']);

    // ################## Approved Organisation Routes ##################
    Route::prefix('organisation')->middleware('organisation.approved')->group(function () {
        // ###### Venue Routes ######
        Route::prefix('venue')->group(function () {
            Route::get('/page-overview', [VenueController::class, 'pageOverview'])->middleware('organisation.permission:venues.read');
            Route::get('/list', [VenueController::class, 'list'])->middleware('organisation.permission:venues.read');
            Route::get('/venue-list-for-events', [VenueController::class, 'listForEvents'])->middleware('organisation.permission:venues.read');
            Route::get('/details/{venue_id}', [VenueController::class, 'details'])->middleware('organisation.permission:venues.read');
            Route::get('/facilities', [VenueController::class, 'getFacilitiesListForVenue'])->middleware('organisation.permission:venues.read');
            Route::get('/suitable-for-options', [VenueController::class, 'getVenueSuitableForOptions'])->middleware('organisation.permission:venues.read');
            Route::post('/create', [VenueController::class, 'create'])->middleware('organisation.permission:venues.create');
            Route::post('/duplicate/{venue_id}', [VenueController::class, 'duplicate'])->middleware('organisation.permission:venues.create');
            Route::post('/create-custom-facilities', [VenueController::class, 'createCustomFacilities'])->middleware('organisation.permission:venues.create');
            Route::delete('/delete-facility/{facility_id}', [VenueController::class, 'deleteCustomFacility'])->middleware('organisation.permission:venues.update');
            Route::post('/create-custom-suitable-for-option', [VenueController::class, 'createCustomSuitableForOption'])->middleware('organisation.permission:venues.create');
            Route::delete('/delete-suitable-for-option/{option_id}', [VenueController::class, 'deleteCustomSuitableForOption'])->middleware('organisation.permission:venues.update');
            Route::post('/update/{venue_id}', [VenueController::class, 'update'])->middleware('organisation.permission:venues.update');
            Route::post('/manage-status', [VenueController::class, 'manageStatus'])->middleware('organisation.permission:venues.update');
            Route::delete('/delete/{venue_id}', [VenueController::class, 'delete'])->middleware('organisation.permission:venues.delete');
        });

        // ###### Event Routes ######
        Route::prefix('event')->group(function () {
            Route::get('/list', [EventController::class, 'list'])->middleware('organisation.permission:events.read,org_clubsites.read,org_clubsites.create,org_clubsites.update');
            Route::get('/details/{event_id}', [EventController::class, 'details'])->middleware('organisation.permission:events.read');
            Route::post('/create', [EventController::class, 'create'])->middleware('organisation.permission:events.create');
            Route::post('/duplicate/{event_id}', [EventController::class, 'duplicate'])->middleware('organisation.permission:events.create');
            Route::post('/update/{event_id}', [EventController::class, 'update'])->middleware('organisation.permission:events.update');
            Route::delete('/delete/{event_id}', [EventController::class, 'delete'])->middleware('organisation.permission:events.delete');
            Route::post('/manage-status/{event_id}', [EventController::class, 'manageStatus'])->middleware('organisation.permission:events.update');
            Route::post('/create-custom-category', [EventController::class, 'createCustomCategory'])->middleware('organisation.permission:events.create');

            // ##### Event Order Routes ######
            Route::prefix('orders')->group(function () {
                Route::get('/detail/{order_id}', [EventController::class, 'orderDetails'])->middleware('organisation.permission:events.read');
                Route::get('/{event_id}', [EventController::class, 'listOrders'])->middleware('organisation.permission:events.read');
            });

            // ##### Event Ticket Routes ######
            Route::prefix('ticket')->group(function () {
                Route::post('/create', [EventController::class, 'createTicket'])->middleware('organisation.permission:events.update');
                Route::get('/list/{event_id}', [EventController::class, 'listTickets'])->middleware('organisation.permission:events.read');
                Route::post('/update/{ticket_id}', [EventController::class, 'updateTicket'])->middleware('organisation.permission:events.update');
                Route::delete('/delete/{ticket_id}', [EventController::class, 'deleteTicket'])->middleware('organisation.permission:events.update');

                // ##### Event Ticket Offer Routes ######
                Route::prefix('offer')->group(function () {
                    Route::post('/create', [EventController::class, 'createTicketOffer'])->middleware('organisation.permission:events.update');
                    Route::get('/list/{ticket_id}', [EventController::class, 'listTicketOffers'])->middleware('organisation.permission:events.read');
                    Route::post('/update/{offer_id}', [EventController::class, 'updateTicketOffer'])->middleware('organisation.permission:events.update');
                    Route::delete('/delete/{offer_id}', [EventController::class, 'deleteTicketOffer'])->middleware('organisation.permission:events.update');
                });
            });

            // ######## Refund List Routes ########
            Route::prefix('{event_id}/refunds')->group(function () {
                Route::get('/', [EventController::class, 'listRefunds'])->middleware('organisation.permission:events.read');
            });
        });

        // ###### Organisation Stripe Connect Routes ######
        Route::prefix('stripe')->group(function () {
            Route::post('/account/detail', [OrgStripeController::class, 'stripeAccountDetail']);
            Route::post('/account/create', [OrgStripeController::class, 'createConnectedAccount']);
            Route::post('/account/onboarding-link', [OrgStripeController::class, 'createOnboardingLink']);
        });

        // ###### Domain / Vercel Routes ######
        Route::prefix('domain')->group(function () {
            Route::post('/connect', [DomainController::class, 'connect']);
            Route::get('/status', [DomainController::class, 'status']);
        });

        // ###### Site Routes ######
        Route::prefix('site')->group(function () {
            Route::get('/list', [SiteController::class, 'list'])
                ->middleware('organisation.permission:org_clubsites.read,org_clubsites.create,org_clubsites.update');
            Route::post('/create', [SiteController::class, 'create'])
                ->middleware('organisation.permission:org_clubsites.create');
            Route::post('/update/{site_id}', [SiteController::class, 'update'])
                ->middleware('organisation.permission:org_clubsites.update');

            Route::prefix('news')->group(function () {
                Route::get('/list', [SiteNewsController::class, 'list'])
                    ->middleware('organisation.permission:org_clubsites.read,org_clubsites.create,org_clubsites.update');
                Route::post('/create', [SiteNewsController::class, 'create'])
                    ->middleware('organisation.permission:org_clubsites.create,org_clubsites.update');
                Route::post('/update/{news_id}', [SiteNewsController::class, 'update'])
                    ->middleware('organisation.permission:org_clubsites.update');
                Route::post('/manage-status/{news_id}', [SiteNewsController::class, 'manageStatus'])
                    ->middleware('organisation.permission:org_clubsites.update');
                Route::delete('/delete/{news_id}', [SiteNewsController::class, 'delete'])
                    ->middleware('organisation.permission:org_clubsites.update,org_clubsites.delete');
            });

            Route::prefix('support-contact')->group(function () {
                Route::get('/list', [SiteSupportContactController::class, 'list'])
                    ->middleware('organisation.permission:org_clubsites.read,org_clubsites.create,org_clubsites.update');
                Route::post('/create', [SiteSupportContactController::class, 'create'])
                    ->middleware('organisation.permission:org_clubsites.create,org_clubsites.update');
                Route::post('/update/{contact_id}', [SiteSupportContactController::class, 'update'])
                    ->middleware('organisation.permission:org_clubsites.update');
                Route::delete('/delete/{contact_id}', [SiteSupportContactController::class, 'delete'])
                    ->middleware('organisation.permission:org_clubsites.update,org_clubsites.delete');
            });

            Route::prefix('banner')->group(function () {
                Route::get('/', [SiteBannerController::class, 'show'])
                    ->middleware('organisation.permission:org_clubsites.read,org_clubsites.create,org_clubsites.update');
                Route::post('/save', [SiteBannerController::class, 'save'])
                    ->middleware('organisation.permission:org_clubsites.create,org_clubsites.update');
            });

            Route::get('/category', [SiteCategoryController::class, 'show'])
                ->middleware('organisation.permission:org_clubsites.read,org_clubsites.create,org_clubsites.update');
            Route::post('/category-update', [SiteCategoryController::class, 'update'])
                ->middleware('organisation.permission:org_clubsites.create,org_clubsites.update');

            Route::post('/{site_id}/update-domain', [SiteController::class, 'updateDomain'])
                ->middleware('organisation.permission:org_clubsites.update');
            Route::get('/{site_id}/domain-status', [SiteController::class, 'checkDomainStatus'])
                ->middleware('organisation.permission:org_clubsites.read,org_clubsites.update');
            Route::post('/{site_id}/refresh-domain', [SiteController::class, 'refreshDomain'])
                ->middleware('organisation.permission:org_clubsites.update');
        });

        // ########### Role & Permission Routes ###########
        Route::prefix('role-permission')->group(function () {
            Route::get('/permissions-list', [RolePermissionController::class, 'getAllOrganisationPermissions']);
            Route::get('/get-role-list', [RolePermissionController::class, 'getRoleList']);
            Route::post('/create-role', [RolePermissionController::class, 'createRoleWithPermission']);
            Route::get('/get-role-with-permissions/{role_id}', [RolePermissionController::class, 'getRoleWithPermissions']);
            Route::post('/update/{role_id}', [RolePermissionController::class, 'updateRoleWithPermission']);
        });

        // ########### Staff Member Routes ###########
        Route::prefix('staff-member')->group(function () {
            Route::get('/list', [StaffMemberController::class, 'list']);
            Route::get('/details/{member_id}', [StaffMemberController::class, 'details']);
            Route::post('/create', [StaffMemberController::class, 'create']);
            Route::post('/update/{member_id}', [StaffMemberController::class, 'update']);
            Route::post('/manage-status/{member_id}', [StaffMemberController::class, 'manageStatus']);
        });

        // ########### Customer Routes ###########
        Route::prefix('customer')->group(function () {
            Route::get('/list', [CustomerController::class, 'list'])
                ->middleware('organisation.permission:org_customers.read');
            Route::get('/info/{customer_id}', [CustomerController::class, 'info'])
                ->middleware('organisation.permission:org_customers.read');
            Route::get('/{customer_id}/overview', [CustomerController::class, 'overview'])
                ->middleware('organisation.permission:org_customers.read');
            Route::get('/{customer_id}/orders', [CustomerController::class, 'listOrders'])
                ->middleware('organisation.permission:org_customers.read');
            // ##### Customer Products #########
            Route::get('/{customer_id}/products', [CustomerController::class, 'productList'])
                ->middleware('organisation.permission:org_customers.read');
        });

        // ########### Order Routes ###########
        Route::prefix('orders')->group(function () {
            Route::prefix('refunds')->group(function () {
                Route::get('/list', [OrderController::class, 'listRefunds'])
                    ->middleware('organisation.permission:events.issue_order_refund');
                Route::get('/detail/{refund_id}', [OrderController::class, 'refundDetails'])
                    ->middleware('organisation.permission:events.issue_order_refund');
            });

            Route::post('/{order_id}/issue-refund', [OrderController::class, 'issueRefund'])
                ->middleware('organisation.permission:events.issue_order_refund');
        });
    });

    // ################## Super Admin Platform Routes ##################
    Route::prefix('platform')->middleware('platform')->group(function () {
        Route::prefix('organisation')->group(function () {

            Route::get('/list', [PlatformOrganisationController::class, 'list'])
                ->middleware('platform.permission:organisations.read');

            Route::get('/details/{organisation_id}', [PlatformOrganisationController::class, 'getOrganisationDetailById'])
                ->middleware('platform.permission:organisations.read');

            Route::post('/manage-approve-status', [PlatformOrganisationController::class, 'manageApproveStatus'])
                ->middleware('platform.permission:organisations.manage_approve_status');

        });
    });
});
