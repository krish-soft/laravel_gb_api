<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application
    |--------------------------------------------------------------------------
    */
    'app_name' => 'Green Bazar',

    'success' => 'Success',
    'error'   => 'Error',

    /*
    |--------------------------------------------------------------------------
    | Success Messages
    |--------------------------------------------------------------------------
    */
    'success_messages' => [

        // Generic CRUD
        'success_get'    => 'Data retrieved successfully.',
        'success_create' => 'Resource created successfully.',
        'success_update' => 'Resource updated successfully.',
        'success_delete' => 'Resource deleted successfully.',
        'success_cancel' => 'Resource cancelled successfully.',

        // Authentication
        'success_login'      => 'Login successful.',
        'success_logout'     => 'Logout successful.',
        'success_logout_all' => 'Logged out from all devices successfully.',
        'register_success'   => 'Registration completed successfully.',

        // OTP / Password
        'otp_sent_successfully'       => 'OTP sent successfully.',
        'password_reset_successfully' => 'Password reset successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    */
    'error_messages' => [


        // Authentication & Authorization
        'unauthenticated'             => 'Unauthenticated. Token or session expired. Please log in.',
        'unauthorized_access'         => 'You are not authorized to access this resource.',
        'unauthorized_access_admin'   => 'You are not authorized to access this admin resource.',

        // Generic Errors
        'something_went_wrong' => 'Something went wrong. Please try again later.',
        'already_exists'       => 'The resource you are trying to create already exists.',
        'cannot_delete_used_in_transactions' => 'Cannot delete this resource as it is used in existing transactions.',
        'maintenance_mode' => 'The application is currently under maintenance. Please try again later.',

        'missing_configuration_ms_api_key' => 'Missing configuration MS-API key',
        'invalid_configuration_ms_api_key' => 'Invalid configuration MS-API key',

        // Account & Login
        'invalid_credentials'       => 'Invalid credentials provided.',
        'account_not_associate'     => 'No account is associated with the provided information.',
        'account_already_registered' => 'An account with these details already exists.',
        'account_inactive'        => 'Your account is inactive.',

        // OTP
        'failed_to_send_otp'   => 'Failed to send OTP. Please try again later.',
        'invalid_or_expired_otp' => 'The OTP is invalid or has expired.',

        // WorkSite / Business Logic
        'work_site_exists' => 'A work site already exists for this location.',
        'address_exists' => 'An address already exists for this location.',

        // Market Listing
        'item_not_exist_in_list' => 'Product does not exist in this listing.',
        'package_not_exist_in_list' => 'Package does not exist in this listing.',
        'package_qty_less_than_sold' => 'Package quantity cannot be less than sold quantity (:sold_qty).',
        'invalid_stock_update' => 'Invalid stock update operation.',
        'cannot_delete_listing_with_sold_stock' => 'Cannot delete listing with sold stock.',
    ],

];
