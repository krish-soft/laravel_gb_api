<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payment</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>

<body>

    <script>
        const checkout = @json($checkout);
        const statusUrl = "{{ $statusUrl }}";

        function closeApp() {
            try {
                window.Android.closeWebView();
            } catch (e) {}
            try {
                window.webkit.messageHandlers.close.postMessage(null);
            } catch (e) {}
            window.close();
        }

        // ✅ Disable EMI
        checkout.method = {
            netbanking: true,
            card: true,
            upi: true,

            emi: false, // ❌ disable EMI
            wallet: false, // ❌ disable wallets (Paytm, PhonePe wallet, etc.)
            paylater: false // ❌ disable Pay Later (Simpl, LazyPay, etc.)
        };

        // ✅ Payment success
        checkout.handler = function(response) {

            // 🔐 Send payment data to backend (IMPORTANT)
            fetch(statusUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    razorpay_payment_id: response.razorpay_payment_id,
                    razorpay_order_id: response.razorpay_order_id,
                    razorpay_signature: response.razorpay_signature
                })
            }).finally(() => {
                closeApp(); // 🔥 instant close
            });
        };

        // ✅ User cancelled / failed
        checkout.modal = {
            ondismiss: function() {
                closeApp();
            }
        };

        // 🚀 Open checkout
        new Razorpay(checkout).open();
    </script>

</body>

</html>
