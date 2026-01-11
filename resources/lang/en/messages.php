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
        'success_login'      => 'Login completed successfully.',
        'success_logout'     => 'Logout completed successfully.',
        'success_logout_all' => 'You have been logged out from all devices successfully.',
        'register_success'   => 'Registration completed successfully.',

        // OTP / Password
        'otp_sent_successfully'       => 'A one-time password (OTP) has been sent successfully.',
        'password_reset_successfully' => 'Password has been reset successfully.',

        // KYC
        'kyc_submitted' => 'KYC information submitted successfully and is under review.',
        'kyc_updated'   => 'KYC information updated successfully.',
        'kyc_approved'  => 'KYC has been approved successfully.',
        'kyc_rejected'  => 'KYC has been rejected.',
        'kyc_deleted'   => 'KYC record deleted successfully.',

        /* ===================== BANK ===================== */
        'bank_added' => 'Bank account added successfully.',
        'bank_updated' => 'Bank account updated successfully.',
        'bank_reviewed' =>   'Bank account reviewed successfully.',
        'bank_deleted' =>  'Bank account deleted successfully.',
        'bank_primary_set' => 'Primary bank account updated successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    */
    'error_messages' => [

        // Authentication & Authorization
        'unauthenticated'           => 'Authentication required. Your session or token has expired. Please log in again.',
        'unauthorized_access'       => 'You are not authorized to access this resource.',
        'unauthorized_access_admin' => 'You are not authorized to access this administrative resource.',

        //
        'not_adult'            => 'You must be at least 18 years of age to perform this action.',
        'force_app_update'    => 'A new version of the app (version :version) is available. Please update to continue using the app.',
        'invalid_locale'     => 'The specified locale is not supported.',
        
        // Generic Errors
        'something_went_wrong' => 'An unexpected error occurred. Please try again later.',
        'already_exists'       => 'The requested resource already exists.',
        'cannot_delete_used_in_transactions' => 'This resource cannot be deleted because it is associated with existing transactions.',
        'maintenance_mode'     => 'The application is currently under maintenance. Please try again later.',
        'unauthorized_action'  => 'You are not authorized to perform this action.',


        // Configuration
        'missing_configuration_ms_api_key' => 'Required MS API key configuration is missing.',
        'invalid_configuration_ms_api_key' => 'The configured MS API key is invalid.',

        // Account & Login
        'invalid_credentials'         => 'The provided credentials are invalid.',
        'account_not_associate'       => 'No account is associated with the provided information.',
        'account_already_registered'  => 'An account with the provided details already exists.',
        'account_inactive'            => 'Your account is currently inactive. Please contact support.',

        // OTP
        'failed_to_send_otp'     => 'Failed to send OTP. Please try again later.',
        'invalid_or_expired_otp' => 'The provided OTP is invalid or has expired.',

        // KYC Lifecycle
        'kyc_already_under_review'   => 'KYC information has already been submitted and is currently under review.',
        'kyc_not_found'              => 'KYC record could not be found.',
        'invalid_kyc_status'         => 'The provided KYC status is invalid.',
        'kyc_cannot_delete_approved' => 'An approved KYC record cannot be deleted.',
        'kyc_not_approved'           => 'KYC is not approved. Access to this resource is restricted.',

        // Aadhaar
        'invalid_aadhaar_number'  => 'The provided Aadhaar number is invalid.',
        'aadhaar_images_required' => 'Both front and back images of the Aadhaar card are required.',

        // PAN
        'invalid_pan_card_format' => 'The provided PAN card number format is invalid.',
        'pan_card_image_required' => 'PAN card image is required.',

        // Uniqueness / Conflicts
        'document_already_registered' => 'An account is already registered with the provided document.',


        /* ===================== BANK ===================== */
        'bank_verified_locked' => 'Verified bank details cannot be modified.',
        'invalid_bank_status' => 'Invalid bank status provided.',
        'primary_bank_delete_forbidden' => 'Primary bank account cannot be deleted.',
        'unauthorized_action' => 'You are not authorized to perform this action.',

        'bank_already_exists' => 'Bank details already exists. Only one bank account is allowed.',
    ],

];
