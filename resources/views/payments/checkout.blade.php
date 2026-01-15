<!doctype html>
<html>
<head>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>
<script>
    const options = @json($checkout);

    options.handler = function () {
        // DO NOTHING
        // webhook decides everything
    };

    options.modal = {
        ondismiss: function () {
            // user cancelled → cron will handle
        }
    };

    new Razorpay(options).open();
</script>
</body>
</html>
