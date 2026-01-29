<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payment</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 80px;
            background: #f7f7f7;
        }

        .box {
            display: none;
        }

        button {
            margin-top: 20px;
            padding: 10px 20px;
            font-size: 16px;
        }
    </style>
</head>

<body>

    <h2 id="start">Opening payment…</h2>

    <div id="result" class="box">
        <h2 id="title"></h2>
        <p id="desc"></p>
        <button onclick="closeApp()">Close</button>
    </div>

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

        async function fetchResultOnce() {
            try {
                const res = await fetch(statusUrl);
                if (!res.ok) throw '';

                const data = await res.json();

                document.getElementById('start').style.display = 'none';
                document.getElementById('result').style.display = 'block';

                if (data.status === 'paid') {
                    document.getElementById('title').innerText = 'Payment Completed';
                    document.getElementById('desc').innerText =
                        data.order_number ? `Order Number: ${data.order_number}` : '';
                } else {
                    document.getElementById('title').innerText = 'Payment Pending';
                    document.getElementById('desc').innerText =
                        'You may safely close this window.';
                }
            } catch (e) {
                closeApp(); // fallback
            }
        }

        checkout.handler = function() {
            // Razorpay success UI closed → now check backend ONCE
            setTimeout(fetchResultOnce, 1000);
        };

        checkout.modal = {
            ondismiss: function() {
                // User closed / failed / timeout → still check backend ONCE
                setTimeout(fetchResultOnce, 1000);
            }
        };

        new Razorpay(checkout).open();
    </script>

</body>

</html>
