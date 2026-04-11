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

        .total {
            background: #fafafa;
            font-weight: bold;
        }

        .flowbox {
            margin-top: 8px;
            padding: 6px;
            border: 1px solid #ccc;
            background: #f9f9f9;
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



    {{-- PRODUCT SUMMARY --}}

    <div class="section">

        <b>Product Movement by Shipment Type</b>

        @foreach ($product_summary as $group)
            <div class="flowbox">

                <b>{{ strtoupper($group['shipment_type']) }}</b>

                <table>

                    <tr>
                        <th width="40%">Product</th>
                        <th width="10%">Pack</th>
                        <th width="10%">Unit</th>
                        <th class="right">Packages</th>
                        <th class="right">Qty</th>
                        <th class="right">Weight</th>
                    </tr>

                    @foreach ($group['products'] as $p)
                        <tr>

                            <td>
                                <b>{{ $p['product']['name'] ?? 'N/A' }}</b><br>
                                <span class="meta">{{ $p['product']['product_code'] ?? '' }}</span>
                            </td>

                            <td class="center">{{ $p['pack_size'] }}</td>

                            <td class="center">{{ $p['pack_unit'] }}</td>

                            <td class="right">{{ $p['packages'] }}</td>

                            <td class="right">{{ $p['qty'] }}</td>

                            <td class="right">{{ $p['weight'] }}</td>

                        </tr>
                    @endforeach

                </table>

            </div>
        @endforeach

    </div>



    {{-- FLOW SUMMARY --}}

    <div class="section">

        <b>Location Flow Summary</b>

        <table>

            <tr>
                <th width="30%">From</th>
                <th width="30%">To</th>
                <th class="right">Shipments</th>
                <th class="right">Packages</th>
                <th class="right">Qty</th>
                <th class="right">Weight</th>
            </tr>

            @foreach ($flow_summary as $f)
                <tr>

                    <td>{{ $f['from'] }}</td>

                    <td>{{ $f['to'] }}</td>

                    <td class="right">{{ $f['shipments'] }}</td>

                    <td class="right">{{ $f['packages'] }}</td>

                    <td class="right">{{ $f['qty'] }}</td>

                    <td class="right">{{ $f['weight'] }}</td>

                </tr>
            @endforeach

        </table>

    </div>



    {{-- FLOW DETAILS --}}

    <div class="section">

        <b>Shipment Flow Details</b>

        @foreach ($flow_details as $flow)
            <div class="flowbox">

                <b>
                    {{ $flow['from']['addr_name'] ?? '' }}
                    →
                    {{ $flow['to']['addr_name'] ?? '' }}
                </b>

                @foreach ($flow['shipments'] as $s)
                    <table>

                        <tr>
                            <th width="40%">Shipment</th>
                            <th width="15%">Date</th>
                            <th width="15%">Type</th>
                            <th width="15%">Status</th>
                        </tr>

                        <tr>

                            <td>{{ $s['shipment_number'] }}</td>

                            <td>{{ $s['shipment_date'] }}</td>

                            <td>{{ $s['shipment_type'] }}</td>

                            <td>{{ $s['status'] }}</td>

                        </tr>

                    </table>


                    <table>

                        <tr>
                            <th width="40%">Product</th>
                            <th width="10%">Pack</th>
                            <th width="10%">Unit</th>
                            <th class="right">Qty</th>
                            <th class="right">Weight</th>
                        </tr>

                        @foreach ($s['items'] as $i)
                            <tr>

                                <td>
                                    <b>{{ $i['product']['name'] ?? 'N/A' }}</b><br>
                                    <span class="meta">{{ $i['product']['product_code'] ?? '' }}</span>
                                </td>

                                <td class="center">{{ $i['pack_size'] }}</td>

                                <td class="center">{{ $i['pack_unit'] }}</td>

                                <td class="right">{{ $i['qty'] }}</td>

                                <td class="right">{{ $i['weight'] }}</td>

                            </tr>
                        @endforeach


                        <tr class="total">

                            <td colspan="3">Shipment Total</td>

                            <td class="right">{{ $s['total']['qty'] }}</td>

                            <td class="right">{{ $s['total']['weight'] }}</td>

                        </tr>

                    </table>
                @endforeach

            </div>
        @endforeach

    </div>



</body>

</html>
