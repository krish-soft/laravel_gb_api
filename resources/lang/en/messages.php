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

        // KYC
        'kyc_submitted' => 'KYC submitted successfully',
        'kyc_updated'   => 'KYC updated successfully',
        'kyc_approved'  => 'KYC approved successfully',
        'kyc_rejected'  => 'KYC rejected successfully',
        'kyc_deleted'   => 'KYC deleted successfully',


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
        'unauthorized_action' => 'You are not authorized to perform this action',


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

        // KYC lifecycle
        'kyc_already_under_review' => 'KYC is already submitted and under review',
        'kyc_not_found'            => 'KYC record not found',
        'invalid_kyc_status'       => 'Invalid KYC status',
        'kyc_cannot_delete_approved' => 'Approved KYC cannot be deleted',

        // Aadhaar
        'invalid_aadhaar_number'   => 'Invalid Aadhaar number',
        'aadhaar_images_required'  => 'Aadhaar front and back images are required',

        // PAN
        'invalid_pan_card_format'  => 'Invalid PAN card format',
        'pan_card_image_required'  => 'PAN card image is required',

        // Uniqueness / conflicts
        'account_already_registered' => 'Account already registered with this document',

        // Generic
        'unauthorized_action'      => 'You are not authorized to perform this action',




    ],

];
