<?php

namespace App\Enum\Common;

enum OtpPurposeEnum: string
{
    //

    case USER_REGISTRATION = 'user_registration'; // OTP for user registration
    case FORGET_PASSWORD = 'forget_password'; // OTP for forgetting password


    case DELIVERY_CONFIRMATION = 'delivery_confirmation'; // OTP for confirming delivery by driver
    case ORDER_CANCELLATION_CONFIRMATION = 'order_cancellation_confirmation'; // OTP for confirming order cancellation by buyer
    case ORDER_RETURN_CONFIRMATION = 'order_return_confirmation'; // OTP for confirming order return by buyer


}
