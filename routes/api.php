<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\Organisation\EventController;
use App\Http\Controllers\Api\Organisation\OrganisationController;
use App\Http\Controllers\Api\Organisation\OrgStripeController;
use App\Http\Controllers\Api\Organisation\RolePermissionController;
use App\Http\Controllers\Api\Organisation\StaffMemberController;
use App\Http\Controllers\Api\Organisation\VenueController;
use App\Http\Controllers\Api\Platform\OrganisationController as PlatformOrganisationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Public\EventController as PublicEventController;
use App\Http\Controllers\Api\Webhooks\Stripe\StripeAccountWebhook;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login.store');

Route::post('/organiser/register', [AuthController::class, 'registerOrganiser'])
    ->name('organiser.register');

Route::post('/organiser/verify-email', [AuthController::class, 'verifyOrganiserEmail'])
    ->name('organiser.verify-email');

Route::post('/account-webhooks', StripeAccountWebhook::class)
    ->name('stripe.account-webhooks');

// ################## Public Venue Option Lists ##################
Route::prefix('venue')->group(function () {
    Route::get('/types', [VenueController::class, 'getVenueTypeList']);
    Route::get('/facilities', [VenueController::class, 'getFacilitiesListForVenue']);
    Route::get('/suitable-for-options', [VenueController::class, 'getVenueSuitableForOptions']);
});

// ################## Public Event Option Lists ##################
Route::prefix('event')->group(function () {
    Route::get('/categories', [EventController::class, 'getEventCategoryList']);
});

// ################## Public Customer Catalog (no auth) ##################
Route::prefix('public')->group(function () {
    Route::prefix('events')->group(function () {
        Route::get('/list', [PublicEventController::class, 'list']);
        Route::get('/details/{unique_id}', [PublicEventController::class, 'details']);
    });
});

Route::middleware('jwt')->group(function () {
    Route::get('/user', function (Request $request) {
        return ApiResponse::success(data: UserResource::make($request->user()));
    })->name('api.user');

    Route::get('/profile/details', [ProfileController::class, 'details'])
        ->name('profile.details');

    Route::prefix('media')->group(function () {
        Route::post('/upload', [MediaController::class, 'upload'])->name('media.upload');
        Route::delete('/', [MediaController::class, 'delete'])->name('media.delete');
    });

    Route::get('/my-org/details', [OrganisationController::class, 'getOrganisationDetail']);

    // ################## Approved Organisation Routes ##################
    Route::prefix('organisation')->middleware('organisation.approved')->group(function () {
        // ###### Venue Routes ######
        Route::get('/venue/list', [VenueController::class, 'list'])->middleware('organisation.permission:venues.read');
        Route::post('/venue/create', [VenueController::class, 'create'])->middleware('organisation.permission:venues.create');
        Route::post('/venue/update/{venue_id}', [VenueController::class, 'update'])->middleware('organisation.permission:venues.update');
        Route::delete('/venue/delete/{venue_id}', [VenueController::class, 'delete'])->middleware('organisation.permission:venues.delete');

        // ###### Event Routes ######
        Route::get('/event/list', [EventController::class, 'list'])->middleware('organisation.permission:events.read');
        Route::post('/event/create', [EventController::class, 'create'])->middleware('organisation.permission:events.create');
        Route::post('/event/update/{event_id}', [EventController::class, 'update'])->middleware('organisation.permission:events.update');
        Route::delete('/event/delete/{event_id}', [EventController::class, 'delete'])->middleware('organisation.permission:events.delete');
        Route::post('/event/manage-status/{event_id}', [EventController::class, 'manageStatus'])->middleware('organisation.permission:events.update');

        // ##### Event Ticket Routes ######
        Route::post('/event/ticket/create', [EventController::class, 'createTicket'])->middleware('organisation.permission:events.update');
        Route::get('/event/ticket/list/{event_id}', [EventController::class, 'listTickets'])->middleware('organisation.permission:events.read');
        Route::post('/event/ticket/update/{ticket_id}', [EventController::class, 'updateTicket'])->middleware('organisation.permission:events.update');
        Route::delete('/event/ticket/delete/{ticket_id}', [EventController::class, 'deleteTicket'])->middleware('organisation.permission:events.update');

        // ##### Event Ticket Offer Routes ######
        Route::post('/event/ticket/offer/create', [EventController::class, 'createTicketOffer'])->middleware('organisation.permission:events.update');
        Route::get('/event/ticket/offer/list/{ticket_id}', [EventController::class, 'listTicketOffers'])->middleware('organisation.permission:events.read');
        Route::post('/event/ticket/offer/update/{offer_id}', [EventController::class, 'updateTicketOffer'])->middleware('organisation.permission:events.update');
        Route::delete('/event/ticket/offer/delete/{offer_id}', [EventController::class, 'deleteTicketOffer'])->middleware('organisation.permission:events.update');

        // ###### Organisation Stripe Connect Routes ######
        Route::prefix('stripe')->group(function () {
            Route::post('/account/detail', [OrgStripeController::class, 'stripeAccountDetail']);
            Route::post('/account/create', [OrgStripeController::class, 'createConnectedAccount']);
            Route::post('/account/onboarding-link', [OrgStripeController::class, 'createOnboardingLink']);
        });

        // ########### Role & Permission Routes ###########
        Route::prefix('role-permission')->group(function () {
            Route::get('/permissions-list', [RolePermissionController::class, 'getAllOrganisationPermissions']);
            Route::get('/get-role-list', [RolePermissionController::class, 'getRoleList']);
            Route::get('/get-role-with-permissions/{role_slug}', [RolePermissionController::class, 'getRoleWithPermissions']);
            Route::post('/create-role', [RolePermissionController::class, 'createRoleWithPermission']);
            Route::post('/update/{role_slug}', [RolePermissionController::class, 'updateRoleWithPermission']);
        });

        // ########### Staff Member Routes ###########
        Route::prefix('staff-member')->group(function () {
            Route::get('/list', [StaffMemberController::class, 'list']);
            Route::get('/details/{member_id}', [StaffMemberController::class, 'details']);
            Route::post('/create', [StaffMemberController::class, 'create']);
            Route::post('/update/{member_id}', [StaffMemberController::class, 'update']);
            Route::post('/manage-status/{member_id}', [StaffMemberController::class, 'manageStatus']);
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
