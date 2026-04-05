<?php

return [

    //  Hindi translation of messages for Khet Bajar API responses

    /*
    |--------------------------------------------------------------------------
    | App
    |--------------------------------------------------------------------------
    */
    'app_name' => 'Khet Bajar',

    'success' => 'सफलता',
    'error'   => 'त्रुटि',

    /*
    |--------------------------------------------------------------------------
    | Success Messages
    |--------------------------------------------------------------------------
    */
    'success_messages' => [

        /* Generic CRUD */
        'success_get'    => 'डेटा सफलतापूर्वक प्राप्त हुआ।',
        'success_create' => 'रिसोर्स सफलतापूर्वक बनाया गया।',
        'success_update' => 'रिसोर्स सफलतापूर्वक अपडेट किया गया।',
        'success_delete' => 'रिसोर्स सफलतापूर्वक हटाया गया।',
        'success_cancel' => 'रिसोर्स सफलतापूर्वक रद्द किया गया।',
        'success_reverse' => 'रिसोर्स ट्रांज़ैक्शन सफलतापूर्वक रिवर्स किया गया।',

        /* Authentication */
        'success_login'      => 'लॉगिन सफलतापूर्वक पूर्ण हुआ।',
        'success_logout'     => 'लॉगआउट सफलतापूर्वक पूर्ण हुआ।',
        'success_logout_all' => 'आपको सभी डिवाइस से सफलतापूर्वक लॉगआउट कर दिया गया है।',
        'register_success'   => 'पंजीकरण सफलतापूर्वक पूर्ण हुआ।',

        /* OTP & Password */
        'otp_sent_successfully'       => 'वन-टाइम पासवर्ड (OTP) सफलतापूर्वक भेजा गया है।',
        'password_reset_successfully' => 'पासवर्ड सफलतापूर्वक रीसेट किया गया है।',

        /* KYC */
        'kyc_submitted' => 'KYC जानकारी सफलतापूर्वक जमा की गई है और समीक्षा के अंतर्गत है।',
        'kyc_updated'   => 'KYC जानकारी सफलतापूर्वक अपडेट की गई है।',
        'kyc_approved'  => 'KYC सफलतापूर्वक स्वीकृत किया गया है।',
        'kyc_rejected'  => 'KYC अस्वीकृत कर दिया गया है।',
        'kyc_deleted'   => 'KYC रिकॉर्ड सफलतापूर्वक हटाया गया।',

        /* Bank */
        'bank_added'       => 'बैंक खाता सफलतापूर्वक जोड़ा गया।',
        'bank_updated'     => 'बैंक खाता सफलतापूर्वक अपडेट किया गया।',
        'bank_reviewed'    => 'बैंक खाते की सफलतापूर्वक समीक्षा की गई।',
        'bank_deleted'     => 'बैंक खाता सफलतापूर्वक हटाया गया।',
        'bank_primary_set' => 'प्राथमिक बैंक खाता सफलतापूर्वक अपडेट किया गया।',

        /* Cart */
        'cart_fetched'      => 'कार्ट सफलतापूर्वक प्राप्त हुआ।',
        'cart_item_added'   => 'आइटम सफलतापूर्वक कार्ट में जोड़ा गया।',
        'cart_item_updated' => 'कार्ट आइटम सफलतापूर्वक अपडेट किया गया।',
        'cart_item_removed' => 'आइटम सफलतापूर्वक कार्ट से हटाया गया।',
        'cart_cleared'      => 'कार्ट सफलतापूर्वक खाली किया गया।',

        /* Checkout & Order */
        'checkout_preview' => 'चेकआउट प्रीव्यू सफलतापूर्वक तैयार किया गया।',
        'order_created'    => 'ऑर्डर सफलतापूर्वक किया गया।',
        'proceed_to_payment' => 'अपना ऑर्डर पूरा करने के लिए कृपया पेमेंट करें।',
        'success_preview'   => 'प्रीव्यू सफलतापूर्वक तैयार किया गया।',
        'invalid_checkout_charges' => 'अमान्य चेकआउट शुल्क की गणना की गई।',

        /* Payment */
        'payment_initiated' => 'पेमेंट सफलतापूर्वक शुरू किया गया।',
        'payment_completed' => 'पेमेंट सफलतापूर्वक पूर्ण हुआ।',

        /* Listing */
        'listing_created'   => 'लिस्टिंग सफलतापूर्वक बनाई गई।',
        'listing_updated'   => 'लिस्टिंग सफलतापूर्वक अपडेट की गई।',
        'listing_cancelled' => 'लिस्टिंग सफलतापूर्वक रद्द की गई।',

        /* Package */
        'package_updated'   => 'पैकेज सफलतापूर्वक अपडेट किया गया।',
        'package_cancelled' => 'पैकेज सफलतापूर्वक रद्द किया गया।',

        'success_upload'    => 'फाइल सफलतापूर्वक अपलोड की गई।',
        'settlement_batch_created' => 'सेटलमेंट बैच सफलतापूर्वक बनाया गया।',

        'thanks_for_your_feedback' => 'आपके फीडबैक के लिए धन्यवाद!',

        'followed_successfully' => 'सफलतापूर्वक फॉलो किया गया।',
        'unfollowed_successfully' => 'सफलतापूर्वक अनफॉलो किया गया।',
        'cannot_follow_yourself' => 'आप स्वयं को फॉलो नहीं कर सकते।',


    ],

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    */
    'error_messages' => [

        /* Authentication */
        'unauthenticated'           => 'प्रमाणीकरण आवश्यक है। आपका सेशन या टोकन समाप्त हो गया है। कृपया फिर से लॉगिन करें।',
        'unauthorized_access'       => 'आपको इस रिसोर्स तक पहुँचने की अनुमति नहीं है।',
        'unauthorized_access_admin' => 'आपको इस एडमिन रिसोर्स तक पहुँचने की अनुमति नहीं है।',
        'unauthorized_action'       => 'आपको यह कार्य करने की अनुमति नहीं है।',
        'main_resource_cannot_delete' =>  'मुख्य रिसोर्स को हटाया नहीं जा सकता क्योंकि यह सिस्टम संचालन के लिए आवश्यक है।',
        'invalid_current_password'  => 'दिया गया वर्तमान पासवर्ड गलत है।',
        'not_found'                 => 'मांगा गया रिसोर्स नहीं मिला।',

        /* File Upload */
        'file_upload_failed'        => 'फाइल अपलोड विफल हुआ। कृपया फिर से प्रयास करें।',

        /* User & Policy */
        'not_adult'               => 'इस कार्य को करने के लिए आपकी आयु कम से कम 18 वर्ष होनी चाहिए।',
        'user_detlete_prohibited' => 'नीति के अनुसार यूज़र डिलीट करना निषिद्ध है।',

        /* App State */
        'force_app_update' => 'ऐप का नया संस्करण (संस्करण :version) उपलब्ध है। जारी रखने के लिए कृपया अपडेट करें।',
        'maintenance_mode' => 'एप्लिकेशन वर्तमान में मेंटेनेंस में है। कृपया बाद में प्रयास करें।',
        'invalid_locale'   => 'निर्दिष्ट लोकेल समर्थित नहीं है।',

        /* Generic */
        'something_went_wrong'               => 'एक अप्रत्याशित त्रुटि हुई। कृपया बाद में फिर से प्रयास करें।',
        'already_exists'                     => 'मांगा गया रिसोर्स पहले से मौजूद है।',
        'cannot_delete_used_in_transactions' => 'यह रिसोर्स ट्रांज़ैक्शन से जुड़ा हुआ है, इसलिए हटाया नहीं जा सकता।',
        'current_financial_year_cannot_inactive' => 'वर्तमान सक्रिय वित्तीय वर्ष को निष्क्रिय नहीं किया जा सकता।',
        'only_one_depot_allowed' => 'केवल एक डिपो की अनुमति है।',

        /* Configuration */
        'missing_configuration_ms_api_key' => 'आवश्यक MS API key कॉन्फ़िगरेशन गायब है।',
        'invalid_configuration_ms_api_key' => 'कॉन्फ़िगर की गई MS API key अमान्य है।',

        /* Account */
        'invalid_credentials'        => 'दिए गए क्रेडेंशियल अमान्य हैं।',
        'account_not_associate'      => 'दी गई जानकारी से कोई खाता जुड़ा नहीं है।',
        'account_already_registered' => 'दी गई जानकारी से खाता पहले से पंजीकृत है।',
        'account_inactive'           => 'आपका खाता वर्तमान में निष्क्रिय है। कृपया सपोर्ट से संपर्क करें।',

        /* OTP */
        'failed_to_send_otp'     => 'OTP भेजने में विफलता। कृपया बाद में प्रयास करें।',
        'invalid_or_expired_otp' => 'दिया गया OTP अमान्य है या समाप्त हो चुका है।',

        /* KYC */
        'kyc_already_under_review'   => 'KYC जानकारी पहले से समीक्षा में है।',
        'kyc_not_found'              => 'KYC रिकॉर्ड नहीं मिला।',
        'invalid_kyc_status'         => 'दिया गया KYC स्टेटस अमान्य है।',
        'kyc_cannot_delete_approved' => 'स्वीकृत KYC रिकॉर्ड हटाया नहीं जा सकता।',
        'kyc_not_approved'           => 'KYC स्वीकृत नहीं है। एक्सेस प्रतिबंधित है।',

        /* Aadhaar */
        'invalid_aadhaar_number'  => 'दिया गया आधार नंबर अमान्य है।',
        'aadhaar_images_required' => 'आधार कार्ड की आगे और पीछे दोनों छवियाँ आवश्यक हैं।',

        /* PAN */
        'invalid_pan_card_format' => 'दिया गया PAN कार्ड नंबर फॉर्मेट अमान्य है।',
        'pan_card_image_required' => 'PAN कार्ड की छवि आवश्यक है।',

        /* Bank */
        'bank_verified_locked'          => 'वेरिफाइड बैंक विवरण संशोधित नहीं किए जा सकते।',
        'invalid_bank_status'           => 'दिया गया बैंक स्टेटस अमान्य है।',
        'primary_bank_delete_forbidden' => 'प्राथमिक बैंक खाता हटाया नहीं जा सकता।',
        'bank_already_exists'           => 'बैंक विवरण पहले से मौजूद है। केवल एक बैंक खाता अनुमति है।',

        /* Address */
        'address_exists' => 'इस यूज़र के लिए पता पहले से मौजूद है।',

        /* Cart */
        'cart_locked'     => 'चेकआउट प्रक्रिया के कारण कार्ट लॉक है।',
        'cart_not_active' => 'यूज़र के लिए कोई सक्रिय कार्ट नहीं मिला।',
        'cart_empty'      => 'कार्ट खाली है। आगे बढ़ने के लिए आइटम जोड़ें।',
        'cart_expired' => 'आपका कार्ट समाप्त हो गया है। नया ऑर्डर शुरू करें।',
        'cart_has_invalid_items' => 'आपके कार्ट में अमान्य या अनुपलब्ध आइटम हैं। कृपया चेकआउट से पहले समीक्षा करें।',
        'cart_below_minimum_amount' => 'चेकआउट के लिए आवश्यक न्यूनतम राशि :amount से कम है।',
        'cart_not_active_or_converted' => 'कार्ट खाली है या पहले ही ऑर्डर में परिवर्तित हो चुका है।',
        'invalid_checkout_charges' =>  'अमान्य चेकआउट शुल्क की गणना की गई। कृपया कार्ट की समीक्षा करें।',

        /* Listing */
        'listing_locked'         => 'लिस्टिंग लॉक है या समाप्त हो चुकी है।',
        'listing_not_available'  => 'मांगी गई लिस्टिंग उपलब्ध नहीं है।',
        'listing_terminal_state' => 'लिस्टिंग पूर्ण या निष्क्रिय है और संशोधित नहीं की जा सकती।',
        'listing_has_sales'      => 'लिस्टिंग में बिक्री पूरी हो चुकी है इसलिए रद्द नहीं की जा सकती।',
        'listing_flags_locked'   => 'कई फ्लैग के कारण लिस्टिंग लॉक है।',
        'product_listing_inactive' => 'प्रोडक्ट लिस्टिंग निष्क्रिय है।',

        /* Package */
        'package_locked'        => 'पैकेज लॉक है।',
        'package_already_sold'  => 'चयनित पैकेज पहले ही बिक चुका है।',
        'package_sold_out'      => 'चयनित पैकेज स्टॉक में नहीं है।',
        'no_packages_left'      => 'कोई सक्रिय पैकेज शेष नहीं है।',
        'no_packages_provided'  => 'कोई पैकेज प्रदान नहीं किया गया।',
        'invalid_package_data'  => 'अमान्य पैकेज डेटा प्रदान किया गया।',
        'sold_qty_not_editable' => 'बिक चुकी मात्रा को संपादित नहीं किया जा सकता।',

        /* Stock */
        'insufficient_stock' => 'मांगी गई मात्रा के लिए पर्याप्त स्टॉक नहीं है।',
        'qty_less_than_sold' => 'मात्रा बिक चुकी मात्रा से कम नहीं हो सकती।',
        'qty_exceeds_max_allowed' => 'मात्रा अधिकतम अनुमत सीमा से अधिक है।',

        /* Checkout & Payment */
        'checkout_failed'        => 'चेकआउट विफल हुआ। कृपया फिर से प्रयास करें।',
        'invalid_payment_method' => 'अमान्य पेमेंट विधि।',
        'payment_processed'      => 'पेमेंट पहले ही प्रोसेस हो चुका है।',
        'payment_failed'         => 'पेमेंट विफल हुआ। कृपया फिर से प्रयास करें।',
        'payment_already_finalized' => 'पेमेंट पहले ही पूरा हो चुका है (पहले से भुगतान किया गया)।',

        /* Order */
        'order_not_found'   => 'ऑर्डर नहीं मिला।',
        'order_not_pending' => 'ऑर्डर प्रोसेस नहीं किया जा सकता।',
        'order_cancelled'   => 'ऑर्डर पहले ही रद्द हो चुका है।',
        'order_cannot_be_reverted' => 'ऑर्डर वापस नहीं किया जा सकता।',

        /* Pricing */
        'invalid_order_amount' => 'अमान्य ऑर्डर राशि।',
        'invalid_charge_level' => 'अमान्य चार्ज लेवल।',
        'missing_charge_level_pricing_config' => 'चार्ज लेवल प्राइसिंग कॉन्फ़िगरेशन गायब है।',
        'nothing_to_update'    => 'अपडेट करने के लिए कोई बदलाव नहीं मिला।',
        'reason_required'      => 'इस कार्य के लिए कारण आवश्यक है।',
        'missing_charge_level_code' => 'चार्ज लेवल कोड कॉन्फ़िगरेशन गायब है।',

        /* Wallet */
        'insufficient_wallet_balance' => 'इस ट्रांज़ैक्शन को पूरा करने के लिए वॉलेट बैलेंस पर्याप्त नहीं है।',

        /* Fulfillment Location */
        'invalid_fulfillment_location' => 'निर्दिष्ट फुलफिलमेंट लोकेशन अमान्य या निष्क्रिय है।',
        'driver_vehicle_not_available' => 'डिलीवरी के लिए कोई उपलब्ध ड्राइवर वाहन नहीं मिला।',

        'locked_resource' => 'यह संसाधन लॉक है और इसे संशोधित नहीं किया जा सकता।',
        'invalid_shipment_assign' => 'अमान्य शिपमेंट असाइनमेंट। शिपमेंट्स केवल उसी प्रकार के ड्राइवरों को असाइन किए जा सकते हैं (पिकअप या डिस्पैच)।',
        'cannot_update_self' => 'आप अपनी खुद की खाता विवरण अपडेट नहीं कर सकते।',
        'invalid_packaging' => 'चयनित उत्पाद के लिए निर्दिष्ट पैकेजिंग विवरण अमान्य हैं।',
        'duplicate_entry_found' => 'समान मान संयोजन के साथ एक रिकॉर्ड पहले से मौजूद है।',

        'listing_not_allowed' => 'लिस्टिंग वर्तमान में अनुमत नहीं है। कृपया अनुमत समय के भीतर प्रयास करें।',
        'purchasing_not_allowed' => 'खरीदारी वर्तमान में अनुमत नहीं है। कृपया अनुमत समय के भीतर प्रयास करें।',

        'no_enough_credit_balance' => 'आपका उपलब्ध क्रेडिट बैलेंस इस लेन-देन को पूरा करने के लिए अपर्याप्त है।',


    ],


];
