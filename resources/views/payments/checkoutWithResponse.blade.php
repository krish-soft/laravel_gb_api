<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payment</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>

<body style="margin:0;padding:0;">

    <button onclick="closeApp()" style="position:fixed;top:10px;right:10px;z-index:999;">
        Close
    </button>

    <script>
        const checkout = @json($checkout);
        const statusUrl = "{{ $statusUrl }}";

        /* universal close */
        function closeApp() {

            try {
                window.Android.closeWebView();
            } catch (e) {}
            try {
                window.webkit.messageHandlers.close.postMessage(null);
            } catch (e) {}

            window.close();

        }

        /* disable unwanted payment methods */

        checkout.method = {
            netbanking: true,
            card: true,
            upi: true,

            emi: false,
            wallet: false,
            paylater: false
        };

        /* success handler */

        checkout.handler = function(response) {

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

                closeApp();

            });

        };

        /* create instance */

        var rzp = new Razorpay(checkout);

        /* failure handler */

        rzp.on('payment.failed', function(response) {

            fetch(statusUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    status: "failed",
                    razorpay_order_id: response.error.metadata.order_id,
                    razorpay_payment_id: response.error.metadata.payment_id
                })
            }).finally(() => {

                closeApp();

            });

        });

        /* user cancelled */

        checkout.modal = {
            ondismiss: function() {

                fetch(statusUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        status: "cancelled"
                    })
                }).finally(() => {

                    closeApp();

                });

            }
        };

        /* open checkout */

        rzp.open();

        /* safety timeout */

        setTimeout(function() {

            closeApp();

        }, 180000);
    </script>

</body>

</html>
