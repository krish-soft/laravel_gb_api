<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    <style>
        @page {
            margin: 22px;
        }

        /* body {
            font-family: DejaVu Sans;
            font-size: 11px;
            color: #1a1a1a;
        } */

        @font-face {
            font-family: 'NotoGujarati';
            src: url("{{ public_path('fonts/NotoSansGujarati-Regular.ttf') }}") format('truetype');
        }

        @font-face {
            font-family: 'NotoHindi';
            src: url("{{ public_path('fonts/NotoSansDevanagari-Regular.ttf') }}") format('truetype');
        }

        body {
            font-family: 'NotoGujarati', 'NotoHindi', sans-serif;
            font-size: 11px;
            color: #1a1a1a;
        }

        /* ===== COLORS ===== */

        .green {
            color: #2e7d32;
        }

        .orange {
            color: #ef6c00;
        }

        /* ===== HEADER ===== */

        .header {
            width: 100%;
            border-bottom: 2px solid #2e7d32;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .logo {
            height: 96px;
        }

        .invoice-title {
            font-size: 18px;
            font-weight: bold;
            /* color: #ef6c00; */
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .contact {
            font-size: 10.5px;
            /* line-height: 1.35; */
            color: #222;
        }

        .small {
            font-size: 9px;
            color: #555;
        }

        /* ===== ADDRESS BOX ===== */

        .addr {
            border: 1px solid #2e7d32;
            padding: 7px;
            min-height: 65px;
            /* line-height: 1.35; */
            word-break: break-word;
        }

        /* ===== TABLE ===== */

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .table th {
            background: #e8f5e9;
            border: 1px solid #2e7d32;
            padding: 6px;
        }

        .table td {
            border: 1px solid #ccc;
            padding: 6px;
            vertical-align: top;
        }

        /* ===== SUMMARY ===== */

        .summary-wrap {
            width: 100%;
            margin-top: 12px;
        }

        .payment-mini {
            border: 1px solid #2e7d32;
            padding: 6px;
            font-size: 10.5px;
        }

        .total {
            width: 100%;
            border-collapse: collapse;
        }

        .total td {
            border: 1px solid #2e7d32;
            padding: 7px;
        }

        .total .grand {
            font-weight: bold;
            font-size: 12px;
            border-top: 2px solid #2e7d32;
        }

        /* ===== NEW FOOTER DESIGN ===== */

        .footer {
            margin-top: 18px;
            border-top: 1px solid #2e7d32;
            padding-top: 10px;
        }

        .footer-box {
            bottom: 0;
            border: 1px solid #ccc;
            padding: 8px;
            font-size: 9.5px;
        }

        .sign {
            margin-top: 30px;
            text-align: right;
            font-weight: bold;
        }

        ul.legal {
            margin: 4px 0 0 14px;
            padding: 0;
        }
    </style>
</head>

<body>

    <!-- ================= HEADER ================= -->

    <table class="header">
        <tr>

            <td width="35%">

                {{-- <div class="bold">{{ $business->legal_name }}</div> --}}
                <div class="orange invoice-title">{{ strtoupper($business->trade_name) }}</div>

                @if ($business->billAddress)

                    <div class="contact">

                        {{-- LINE 1 --}}
                        @php
                            $bizLine1 = collect([
                                $business->billAddress->address_line1,
                                $business->billAddress->address_line2,
                            ])
                                ->filter()
                                ->implode(', ');
                        @endphp
                        @if ($bizLine1)
                            {{ $bizLine1 }}<br>
                        @endif


                        {{-- LINE 2 --}}
                        @php
                            $bizLine2 = collect([
                                $business->billAddress->village,
                                $business->billAddress->city,
                                '-' . $business->billAddress->postal_code,
                                $business->billAddress->state,
                            ])
                                ->filter()
                                ->implode(', ');
                        @endphp
                        @if ($bizLine2)
                            {{ $bizLine2 }}<br>
                        @endif


                        {{-- LINE 3 --}}
                        {{-- @php
                            $bizLine3 = collect([$business->billAddress->country])
                                ->filter()
                                ->implode(', ');
                        @endphp
                        @if ($bizLine3)
                            {{ $bizLine3 }}
                        @endif --}}


                        {{-- PHONE SEPARATE --}}
                        @if ($business->billAddress->phone_number)
                            <b>Ph:</b> {{ $business->billAddress->phone_number }}
                        @endif
                        {{-- GSTIN if there --}}
                        @if ($business->gst_number)
                            <br><b>GSTIN:</b> {{ $business->gst_number }}
                        @endif

                        @if ($business->cin_number)
                            <br><b>CIN:</b> {{ $business->cin_number }}
                        @endif
                    </div>

                @endif

            </td>

            <td width="30%" class="center">
                <img src="{{ public_path('images/logo.png') }}" class="logo">
            </td>

            <td width="35%" class="right">

                <div class="invoice-title green">LISTING INVOICE</div>

                <b>Invoice No:</b> {{ $invoice->invoice_number }}<br>
                <b>Date:</b> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}<br>
                <b>Listing Code:</b> {{ $productListing->order_number }}

            </td>

        </tr>
    </table>


    <!-- ================= BILL / SHIP ================= -->

    <table width="100%" style="margin-top:6px;">
        <tr>


            <td width="100%" style="padding-left:4px;">
                <div class="addr">
                    <div class="bold green">Seller</div>

                    @php
                        $shipLine1 = collect([$shippingAddress?->address_line1, $shippingAddress?->address_line2])
                            ->filter()
                            ->implode(', ');

                        $shipLine2 = collect([
                            $shippingAddress?->village,
                            $shippingAddress?->city,
                            $shippingAddress?->postal_code,
                            $shippingAddress?->state,
                        ])
                            ->filter()
                            ->implode(', ');
                        $shipLine3 = null;
                        // $shipLine3 = collect([$shippingAddress?->country])
                        //     ->filter()
                        //     ->implode(', ');
                    @endphp

                    @if ($shippingAddress?->addr_name)
                        {{ $shippingAddress->addr_name }}<br>
                    @endif

                    @if ($shipLine1)
                        {{ $shipLine1 }}<br>
                    @endif

                    @if ($shipLine2)
                        {{ $shipLine2 }}<br>
                    @endif

                    @if ($shipLine3)
                        {{ $shipLine3 }}
                    @endif

                    @if ($shippingAddress?->phone_number)
                        <b>Ph:</b> {{ $shippingAddress->phone_number }}
                    @endif

                </div>
            </td>

        </tr>
    </table>


    <!-- ================= ITEMS TABLE ================= -->

    <table class="table">

        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th>Order Qty</th>
                <th>Sold Qty</th>
                <th>Reverse Qty</th>
                <th>Pack Size</th>
                <th>Pack Price</th>
                <th>Discount</th>
                <th>Taxable</th>
                <th>Tax</th>
                <th>Total</th>
            </tr>
        </thead>

        <tbody>

            @foreach ($productListingPackages as $i => $pkg)
                @php
                    $subtotal = ($pkg->sold_qty - $pkg->reverse_qty) * $pkg->pack_price - ($pkg->discount_amount ?? 0);
                @endphp

                <tr>
                    <td class="center">{{ $i + 1 }}</td>

                    <td>
                        {{ $pkg?->listingItem?->product_name ?? '' }}
                    </td>

                    <td class="center">{{ $pkg->order_qty ?? $pkg->qty }}</td>
                    <td class="center">{{ $pkg->sold_qty ?? 0 }}</td>
                    <td class="center">{{ $pkg->reverse_qty ?? 0 }}</td>

                    <td class="right">{{ number_format($pkg->pack_size, 2) }} {{ $pkg->pack_unit }}</td>

                    <td class="right">{{ number_format($pkg->pack_price, 2) }}</td>

                    <td class="right">{{ number_format($pkg->discount_amount ?? 0, 2) }}</td>

                    <td class="right">
                        {{ number_format($subtotal, 2) }}
                    </td>
                    <td class="right">{{ number_format(0, 2) }}</td>
                    <td class="right bold">
                        {{ number_format($subtotal, 2) }}
                    </td>
                </tr>
            @endforeach

            <tr>
                <td colspan="9" style="border:none;height:6px;"></td>
            </tr>

            @foreach ($charges as $charge)
                <tr>
                    <td></td>
                    <td>{{ $charge->charge_name ?? '' }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>

                    <td class="right">{{ number_format($charge->taxable_amount ?? 0, 2) }}</td>

                    <td class="right">{{ number_format($charge->tax_amount ?? 0, 2) }}</td>

                    <td class="right bold">{{ number_format($charge->total_amount, 2) }}</td>
                </tr>
            @endforeach

        </tbody>
    </table>


    <!-- ================= PAYMENT + TOTAL ================= -->

    <table class="summary-wrap">
        <tr>

            <td width="55%" style="padding-right:6px;">



            </td>

            <td width="45%">

                <table class="total">
                    <tr>
                        <td>Product Value</td>
                        <td class="right">{{ number_format($totalRecevableData->gross_amount, 2) }}</td>
                    </tr>

                    <tr>
                        <td>Platform Charges</td>
                        <td class="right"> - {{ abs(number_format($totalRecevableData->total_charge_amount, 2)) }}
                        </td>
                    </tr>

                    <tr class="grand">
                        <td>Receivable Total</td>
                        <td class="right">{{ number_format($totalRecevableData->net_receivable, 2) }}</td>
                    </tr>
                </table>

            </td>

        </tr>
    </table>


    <!-- ================= NEW FOOTER DESIGN ================= -->

    <div class="footer">

        <div class="footer-box">

            <ul class="legal">
                <li>Agricultural produce items may be non-taxable as per applicable regulations.</li>
                <li>Platform service charges may include applicable taxes.</li>
                <li>This invoice is system generated and issued by the platform operator.</li>
                <li>All disputes subject to platform policies and jurisdiction.</li>
            </ul>

        </div>

        {{-- <div class="sign">
            Authorized Signatory
        </div> --}}

    </div>

</body>

</html>
