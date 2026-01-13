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
        'get'    => 'Data retrieved successfully.',
        'create' => 'Resource created successfully.',
        'update' => 'Resource updated successfully.',
        'delete' => 'Resource deleted successfully.',
        'cancel' => 'Resource cancelled successfully.',

        /* Authentication */
        'login'       => 'Logged in successfully.',
        'logout'      => 'Logged out successfully.',
        'logout_all'  => 'Logged out from all devices successfully.',
        'register'    => 'Registration completed successfully.',

        /* OTP & Password */
        'otp_sent'        => 'OTP sent successfully.',
        'password_reset' => 'Password reset successfully.',

        /* KYC */
        'kyc_submitted' => 'KYC submitted and under review.',
        'kyc_updated'   => 'KYC updated successfully.',
        'kyc_approved'  => 'KYC approved successfully.',
        'kyc_rejected'  => 'KYC rejected.',
        'kyc_deleted'   => 'KYC deleted successfully.',

        /* Bank */
        'bank_added'       => 'Bank account added successfully.',
        'bank_updated'     => 'Bank account updated successfully.',
        'bank_reviewed'    => 'Bank account reviewed successfully.',
        'bank_deleted'     => 'Bank account deleted successfully.',
        'bank_primary_set' => 'Primary bank account updated successfully.',

        /* Cart */
        'cart_fetched'      => 'Cart retrieved successfully.',
        'cart_created'      => 'Cart created successfully.',
        'cart_updated'      => 'Cart updated successfully.',
        'cart_item_added'   => 'Item added to cart successfully.',
        'cart_item_updated' => 'Cart item updated successfully.',
        'cart_item_removed' => 'Item removed from cart successfully.',
        'cart_cleared'      => 'Cart cleared successfully.',

        /* Checkout & Order */
        'checkout_preview' => 'Checkout preview generated successfully.',
        'order_created'    => 'Order placed successfully.',

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
        'unauthenticated'     => 'Authentication required. Please log in again.',
        'unauthorized'        => 'You are not authorized to perform this action.',
        'unauthorized_admin'  => 'You are not authorized to access this administrative resource.',

        /* User & Policy */
        'not_adult'            => 'You must be at least 18 years old.',
        'user_delete_blocked'  => 'User deletion is restricted by policy.',

        /* App State */
        'force_update'   => 'A new app version (:version) is available. Please update to continue.',
        'maintenance'    => 'The system is under maintenance. Please try again later.',
        'invalid_locale' => 'The selected language is not supported.',

        /* Generic */
        'something_went_wrong' => 'Something went wrong. Please try again later.',
        'already_exists'       => 'The resource already exists.',
        'linked_resource'      => 'This resource is linked to transactions and cannot be deleted.',

        /* Configuration */
        'missing_ms_key' => 'MS API key configuration is missing.',
        'invalid_ms_key' => 'MS API key configuration is invalid.',

        /* Account */
        'invalid_credentials' => 'Invalid login credentials.',
        'account_not_found'   => 'No account found with the provided details.',
        'account_exists'      => 'An account already exists with these details.',
        'account_inactive'    => 'Your account is inactive. Please contact support.',

        /* OTP */
        'otp_send_failed' => 'Failed to send OTP.',
        'otp_invalid'     => 'Invalid or expired OTP.',

        /* KYC */
        'kyc_under_review' => 'KYC is already under review.',
        'kyc_not_found'    => 'KYC record not found.',
        'kyc_invalid'      => 'Invalid KYC status.',
        'kyc_delete_block' => 'Approved KYC cannot be deleted.',
        'kyc_not_approved' => 'KYC is not approved.',

        /* Aadhaar & PAN */
        'invalid_aadhaar' => 'Invalid Aadhaar number.',
        'aadhaar_images'  => 'Both Aadhaar front and back images are required.',
        'invalid_pan'     => 'Invalid PAN number format.',
        'pan_image'       => 'PAN card image is required.',

        /* Bank */
        'bank_locked'        => 'Verified bank details cannot be modified.',
        'bank_invalid'       => 'Invalid bank status.',
        'bank_primary_block' => 'Primary bank account cannot be deleted.',
        'bank_exists'        => 'Only one bank account is allowed.',

        /* Cart */
        'cart_locked'     => 'Cart is locked due to checkout.',
        'cart_not_active' => 'Cart is not active.',
        'cart_empty'      => 'Cart is empty.',
        'cart_not_found'  => 'Cart not found.',
        'cart_converted'  => 'Cart already converted to order.',

        /* Listing & Package */
        'listing_locked'        => 'Listing is locked or expired.',
        'listing_unavailable'   => 'Listing is no longer available.',
        'listing_terminal'      => 'Listing cannot be modified.',
        'listing_has_sales'     => 'Listing with sales cannot be cancelled.',
        'listing_not_available' => 'Listing is not available.',

        'package_not_found'     => 'Package not found.',
        'package_sold_out'      => 'Package is already sold out.',
        'package_locked'        => 'Package is locked.',
        'no_packages_left'      => 'No packages available.',
        'package_already_sold'  => 'Package is already sold.',
        'no_packages_provided' => 'No packages provided for charge calculation.',
        'invalid_package_data' => 'Invalid package data provided for charge calculation.',

        /* Stock */
        'insufficient_stock' => 'Insufficient stock available.',
        'qty_less_than_sold' => 'Quantity cannot be less than sold quantity.',

        /* Checkout & Payment */
        'checkout_failed'        => 'Checkout failed.',
        'invalid_payment_method' => 'Invalid payment method.',
        'payment_processed'      => 'Payment already processed.',
        'payment_failed'         => 'Payment failed. Please try again.',

        /* Order */
        'order_not_found'   => 'Order not found.',
        'order_not_pending' => 'Order cannot be processed.',
        'order_cancelled'   => 'Order is already cancelled.',

        /* Common */
        'reason_required'   => 'A valid reason is required.',
        'nothing_to_update' => 'Nothing to update.',

        'invalid_order_amount' => 'Invalid order amount.',
        'invalid_charge_level' => 'Invalid charge level.',
    ],

];
