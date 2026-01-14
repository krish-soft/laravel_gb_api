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

        /* Generic CRUD */
        'success_get'    => 'Data retrieved successfully.',
        'success_create' => 'Resource created successfully.',
        'success_update' => 'Resource updated successfully.',
        'success_delete' => 'Resource deleted successfully.',
        'success_cancel' => 'Resource cancelled successfully.',

        /* Authentication */
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

        /* Cart */
        'cart_fetched'      => 'Cart fetched successfully.',
        'cart_item_added'   => 'Item added to cart successfully.',
        'cart_item_updated' => 'Cart item updated successfully.',
        'cart_item_removed' => 'Item removed from cart successfully.',
        'cart_cleared'      => 'Cart cleared successfully.',

        /* Checkout & Order */
        'checkout_preview' => 'Checkout preview generated successfully.',
        'order_created'    => 'Order placed successfully.',
        'proceed_to_payment' => 'Please proceed to payment to complete your order.',
        'success_preview'   => 'Preview generated successfully.',

        /* Payment */
        'payment_initiated' => 'Payment initiated successfully.',
        'payment_completed' => 'Payment completed successfully.',

        /* Listing */
        'listing_created'   => 'Listing created successfully.',
        'listing_updated'   => 'Listing updated successfully.',
        'listing_cancelled' => 'Listing cancelled successfully.',

        /* Package */
        'package_updated'   => 'Package updated successfully.',
        'package_cancelled' => 'Package cancelled successfully.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    */
    'error_messages' => [

        /* Authentication */
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

        /* Bank */
        'bank_verified_locked'          => 'Verified bank details cannot be modified.',
        'invalid_bank_status'           => 'Invalid bank status provided.',
        'primary_bank_delete_forbidden' => 'Primary bank account cannot be deleted.',
        'bank_already_exists'           => 'Bank details already exist. Only one bank account is allowed.',

        /* Address */
        'address_exists' => 'Address already exists for this user.',

        /* Cart */
        'cart_locked'     => 'Cart is locked due to checkout in progress.',
        'cart_not_active' => 'No active cart found for the user.',
        'cart_empty'      => 'Cart is empty. Please add items to proceed.',

        /* Listing */
        'listing_locked'         => 'Listing is locked or expired.',
        'listing_not_available'  => 'The requested listing is not available.',
        'listing_terminal_state' => 'Listing is completed or inactive and cannot be modified.',
        'listing_has_sales'      => 'Listing has completed sales and cannot be cancelled.',

        /* Package */
        'package_locked'        => 'Package is locked.',
        'package_already_sold'  => 'Selected package is already sold out.',
        'package_sold_out'      => 'The selected package is sold out.',
        'no_packages_left'      => 'No active packages remaining.',
        'no_packages_provided'  => 'No packages provided.',
        'invalid_package_data'  => 'Invalid package data provided.',
        'sold_qty_not_editable' => 'Sold quantity cannot be edited.',

        /* Stock */
        'insufficient_stock' => 'Insufficient stock for the requested quantity.',
        'qty_less_than_sold' => 'Quantity cannot be less than sold quantity.',

        /* Checkout & Payment */
        'checkout_failed'        => 'Checkout failed. Please try again.',
        'invalid_payment_method' => 'Invalid payment method.',
        'payment_processed'      => 'Payment has already been processed.',
        'payment_failed'         => 'Payment failed. Please try again.',

        /* Order */
        'order_not_found'   => 'Order not found.',
        'order_not_pending' => 'Order cannot be processed.',
        'order_cancelled'   => 'Order is already cancelled.',

        /* Pricing */
        'invalid_order_amount' => 'Invalid order amount.',
        'invalid_charge_level' => 'Invalid charge level.',
    ],

];
