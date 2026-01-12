<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App
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

        /* CRUD */
        'success_get'    => 'Data retrieved successfully.',
        'success_create' => 'Resource created successfully.',
        'success_update' => 'Resource updated successfully.',
        'success_delete' => 'Resource deleted successfully.',
        'success_cancel' => 'Resource cancelled successfully.',

        /* Auth */
        'success_login'      => 'Login completed successfully.',
        'success_logout'     => 'Logout completed successfully.',
        'success_logout_all' => 'You have been logged out from all devices successfully.',
        'register_success'   => 'Registration completed successfully.',

        /* OTP & Password */
        'otp_sent_successfully'       => 'A one-time password (OTP) has been sent successfully.',
        'password_reset_successfully' => 'Password has been reset successfully.',

        /* KYC */
        'kyc_submitted' => 'KYC information submitted successfully and is under review.',
        'kyc_updated'   => 'KYC information updated successfully.',
        'kyc_approved'  => 'KYC has been approved successfully.',
        'kyc_rejected'  => 'KYC has been rejected.',
        'kyc_deleted'   => 'KYC record deleted successfully.',

        /* Bank */
        'bank_added'       => 'Bank account added successfully.',
        'bank_updated'     => 'Bank account updated successfully.',
        'bank_reviewed'    => 'Bank account reviewed successfully.',
        'bank_deleted'     => 'Bank account deleted successfully.',
        'bank_primary_set' => 'Primary bank account updated successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    */
    'error_messages' => [

        /* Auth */
        'unauthenticated'           => 'Authentication required. Your session or token has expired. Please log in again.',
        'unauthorized_access'       => 'You are not authorized to access this resource.',
        'unauthorized_access_admin' => 'You are not authorized to access this administrative resource.',
        'unauthorized_action'       => 'You are not authorized to perform this action.',

        /* User & Policy */
        'not_adult'               => 'You must be at least 18 years of age to perform this action.',
        'user_detlete_prohibited' => 'User deletion is prohibited as per policy.',

        /* App State */
        'force_app_update' => 'A new version of the app (version :version) is available. Please update to continue.',
        'maintenance_mode' => 'The application is currently under maintenance. Please try again later.',
        'invalid_locale'   => 'The specified locale is not supported.',

        /* Generic */
        'something_went_wrong'               => 'An unexpected error occurred. Please try again later.',
        'already_exists'                     => 'The requested resource already exists.',
        'cannot_delete_used_in_transactions' => 'This resource is linked to transactions and cannot be deleted.',

        /* Configuration */
        'missing_configuration_ms_api_key' => 'Required MS API key configuration is missing.',
        'invalid_configuration_ms_api_key' => 'The configured MS API key is invalid.',

        /* Account */
        'invalid_credentials'        => 'The provided credentials are invalid.',
        'account_not_associate'      => 'No account is associated with the provided information.',
        'account_already_registered' => 'An account with the provided details already exists.',
        'account_inactive'           => 'Your account is currently inactive. Please contact support.',

        /* OTP */
        'failed_to_send_otp'     => 'Failed to send OTP. Please try again later.',
        'invalid_or_expired_otp' => 'The provided OTP is invalid or has expired.',

        /* KYC */
        'kyc_already_under_review'   => 'KYC information is already under review.',
        'kyc_not_found'              => 'KYC record could not be found.',
        'invalid_kyc_status'         => 'The provided KYC status is invalid.',
        'kyc_cannot_delete_approved' => 'An approved KYC record cannot be deleted.',
        'kyc_not_approved'           => 'KYC is not approved. Access is restricted.',

        /* Aadhaar */
        'invalid_aadhaar_number'  => 'The provided Aadhaar number is invalid.',
        'aadhaar_images_required' => 'Both front and back images of the Aadhaar card are required.',

        /* PAN */
        'invalid_pan_card_format' => 'The provided PAN card number format is invalid.',
        'pan_card_image_required' => 'PAN card image is required.',

        /* Uniqueness */
        'document_already_registered' => 'An account is already registered with the provided document.',
        'address_exists'              => 'Address already exists for this resource.',

        /* Bank */
        'bank_verified_locked'          => 'Verified bank details cannot be modified.',
        'invalid_bank_status'           => 'Invalid bank status provided.',
        'primary_bank_delete_forbidden' => 'Primary bank account cannot be deleted.',
        'bank_already_exists'           => 'Bank details already exist. Only one bank account is allowed.',

        /* Product & Listing */
        'package_locked'           => 'Package is locked.',
        'package_already_sold'     => 'Package has already been sold.',
        'qty_less_than_sold'       => 'Quantity cannot be less than sold.',
        'reason_required'          => 'Reason is required.',
        'no_packages_left'         => 'No active packages remaining.',
        'listing_locked'           => 'Listing is locked or expired.',
        'nothing_to_update'        => 'Nothing to update.',
        'listing_terminal_state'   => 'Listing is completed or inactive and cannot be modified.',
        'listing_has_sales'        => 'Listing has completed sales and cannot be cancelled.',
    ],

];
