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
            color: #1a1a1a;
        }

        .header {
            margin-bottom: 15px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
        }

        .meta {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }

        .section {
            margin-top: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th,
        td {
            border: 1px solid #bbb;
            padding: 5px;
        }

        th {
            background: #f2f2f2;
            font-weight: bold;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .total {
            background: #fafafa;
            font-weight: bold;
        }

        .pack {
            font-size: 10px;
            color: #444;
        }

        .buyerbox {
            margin-top: 10px;
            padding: 6px;
            border: 1px solid #ccc;
            background: #f9f9f9;
        }
    </style>
</head>


<body>


    {{-- HEADER --}}

    <div class="header">

        <div class="title">Shipping Report By Buyer</div>

        <div class="meta">
            Date : {{ $filters['start_date'] }} → {{ $filters['end_date'] }}
        </div>

    </div>



    {{-- PRODUCT SUMMARY --}}

    <div class="section">

        <b>Product Summary</b>

        <table>

            <tr>
                <th width="40%">Product</th>
                <th width="10%">Unit</th>
                <th class="right">Qty</th>
                <th class="right">Shipped</th>
                <th class="right">Weight</th>
                <th class="right">Amount</th>
            </tr>

            @foreach ($product_summary as $p)
                <tr>

                    <td>
                        <b>{{ $p['product']['name'] }}</b><br>
                        <span class="meta">{{ $p['product']['product_code'] }}</span>
                    </td>

                    <td class="center">{{ $p['pack_unit'] }}</td>

                    <td class="right">{{ $p['qty'] }}</td>

                    <td class="right">{{ $p['shipped_qty'] }}</td>

                    <td class="right">
                        {{ $p['weight'] }} {{ $p['pack_unit'] }}
                    </td>

                    <td class="right">
                        {{ number_format($p['amount'], 2) }}
                    </td>

                </tr>
            @endforeach

        </table>

    </div>



    {{-- BUYER REPORTS --}}

    @foreach ($buyer_reports as $buyer)
        <div class="section">

            <div class="buyerbox">

                <b>Buyer :</b> {{ $buyer['buyer']->name }}<br>

                <span class="meta">
                    Code : {{ $buyer['buyer']->user_code }} |
                    Nickname : {{ $buyer['buyer']->nickname }}
                </span>

            </div>


            <table>

                <tr>
                    <th width="20%">Source</th>
                    <th width="15%">Order No</th>
                    <th width="25%">Product</th>
                    <th width="7%">Pack</th>
                    <th width="7%">Unit</th>
                    <th width="7%">Type</th>
                    <th class="right">Qty</th>
                    <th class="right">Shipped</th>
                    <th class="right">Weight</th>
                    <th class="right">Amount</th>
                </tr>


                @foreach ($buyer['items'] as $item)
                    <tr>

                        <td class="center">
                            {{ $item['source_type'] }}
                        </td>

                        <td class="center">
                            {{ $item['source_number'] }}
                        </td>

                        <td>
                            <b>{{ $item['product']['name'] }}</b><br>
                            <span class="meta">{{ $item['product']['product_code'] }}</span>
                        </td>

                        <td class="pack">
                            {{ $item['pack_size'] }}
                        </td>

                        <td>
                            {{ $item['pack_unit'] }}
                        </td>

                        <td>
                            {{ $item['pack_type_unit'] }}
                        </td>

                        <td class="right">
                            {{ $item['qty'] }}
                        </td>

                        <td class="right">
                            {{ $item['shipped_qty'] }}
                        </td>

                        <td class="right">
                            {{ $item['weight'] }} {{ $item['pack_unit'] }}
                        </td>

                        <td class="right">
                            {{ number_format($item['amount'], 2) }}
                        </td>

                    </tr>
                @endforeach



                {{-- BUYER TOTAL --}}

                @foreach ($buyer['total'] as $t)
                    <tr class="total">

                        <td colspan="6">
                            Total {{ $t['pack_unit'] }}
                        </td>

                        <td class="right">
                            {{ $t['qty'] }}
                        </td>

                        <td class="right">
                            {{ $t['shipped_qty'] }}
                        </td>

                        <td class="right">
                            {{ $t['weight'] }} {{ $t['pack_unit'] }}
                        </td>

                        <td class="right">
                            {{ number_format($t['amount'], 2) }}
                        </td>

                    </tr>
                @endforeach


            </table>

        </div>
    @endforeach



    {{-- GRAND TOTAL --}}

    <div class="section">

        <b>Grand Totals</b>

        <table>

            <tr>
                <th width="20%">Unit</th>
                <th class="right">Qty</th>
                <th class="right">Shipped</th>
                <th class="right">Weight</th>
                <th class="right">Amount</th>
            </tr>


            @foreach ($grand_totals as $g)
                <tr class="total">

                    <td class="center">
                        {{ $g['pack_unit'] }}
                    </td>

                    <td class="right">
                        {{ $g['qty'] }}
                    </td>

                    <td class="right">
                        {{ $g['shipped_qty'] }}
                    </td>

                    <td class="right">
                        {{ $g['weight'] }} {{ $g['pack_unit'] }}
                    </td>

                    <td class="right">
                        {{ number_format($g['amount'], 2) }}
                    </td>

                </tr>
            @endforeach

        </table>

    </div>


</body>

</html>
