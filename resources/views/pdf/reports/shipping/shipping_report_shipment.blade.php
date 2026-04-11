<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    <style>
        @page {
            margin: 22px;
        }

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
            color: #222;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
        }

        .meta {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }

        .section {
            margin-top: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        th,
        td {
            border: 1px solid #bbb;
            padding: 4px;
        }

        th {
            background: #f2f2f2;
            font-weight: bold;
        }

        .right {
            text-align: right
        }

        .center {
            text-align: center
        }

        .location-box {
            margin-top: 10px;
            padding: 6px;
            border: 1px solid #ccc;
            background: #fafafa;
        }
    </style>

</head>

<body>



    {{-- HEADER --}}

    <div class="title">Shipment Logistics Report</div>

    <div class="meta">
        Date : {{ $filters['start_date'] }} → {{ $filters['end_date'] }}
    </div>



    {{-- SHIPMENT SUMMARY --}}

    <div class="section">

        <b>Shipment Status Summary</b>

        <table>

            <tr>
                <th width="20%">Shipment Type</th>
                <th width="15%">Status</th>
                <th class="right">Shipments</th>
                <th class="right">Packages</th>
                <th class="right">Qty</th>
                <th class="right">Weight</th>
            </tr>

            @foreach ($shipment_summary as $s)
                <tr>

                    <td>{{ $s['shipment_type'] }}</td>

                    <td>{{ $s['status'] }}</td>

                    <td class="right">{{ $s['shipments'] }}</td>

                    <td class="right">{{ $s['packages'] }}</td>

                    <td class="right">{{ $s['qty'] }}</td>

                    <td class="right">{{ $s['weight'] }}</td>

                </tr>
            @endforeach

        </table>

    </div>



    {{-- PICKUP SUMMARY --}}

    <div class="section">

        <b>Pickup Summary (From Location)</b>

        @foreach ($from_location_summary as $loc)
            <div class="location-box">

                <b>{{ $loc['location'] }}</b>

                <table>

                    <tr>
                        <th width="45%">Product</th>
                        <th width="10%">Pack</th>
                        <th width="10%">Unit</th>
                        <th class="right">Qty</th>
                        <th class="right">Weight</th>
                    </tr>

                    @foreach ($loc['products'] as $p)
                        <tr>

                            <td>
                                <b>{{ $p['product']['name'] ?? 'N/A' }}</b><br>
                                <span class="meta">{{ $p['product']['product_code'] ?? '' }}</span>
                            </td>

                            <td class="center">{{ $p['pack_size'] }}</td>

                            <td class="center">{{ $p['pack_unit'] }}</td>

                            <td class="right">{{ $p['qty'] }}</td>

                            <td class="right">{{ $p['weight'] }}</td>

                        </tr>
                    @endforeach

                </table>

            </div>
        @endforeach

    </div>



    {{-- DELIVERY SUMMARY --}}

    <div class="section">

        <b>Delivery Summary (To Location)</b>

        @foreach ($to_location_summary as $loc)
            <div class="location-box">

                <b>{{ $loc['location'] }}</b>

                <table>

                    <tr>
                        <th width="45%">Product</th>
                        <th width="10%">Pack</th>
                        <th width="10%">Unit</th>
                        <th class="right">Qty</th>
                        <th class="right">Weight</th>
                    </tr>

                    @foreach ($loc['products'] as $p)
                        <tr>

                            <td>
                                <b>{{ $p['product']['name'] ?? 'N/A' }}</b><br>
                                <span class="meta">{{ $p['product']['product_code'] ?? '' }}</span>
                            </td>

                            <td class="center">{{ $p['pack_size'] }}</td>

                            <td class="center">{{ $p['pack_unit'] }}</td>

                            <td class="right">{{ $p['qty'] }}</td>

                            <td class="right">{{ $p['weight'] }}</td>

                        </tr>
                    @endforeach

                </table>

            </div>
        @endforeach

    </div>



</body>

</html>
