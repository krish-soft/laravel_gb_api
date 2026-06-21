<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Accounting Balance Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        .meta { margin-bottom: 12px; color: #555; }
        .summary { margin-bottom: 12px; }
        .summary td { padding: 4px 8px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; }
        th { background: #f3f3f3; text-align: left; }
        .text-right { text-align: right; }
        .danger { color: #b42318; }
        .primary { color: #175cd3; }
        .section-title { font-size: 14px; margin: 12px 0 6px; }
    </style>
</head>
<body>
    <h1>Accounting Balance Report</h1>
    <div class="meta">Generated at: {{ $generatedAt }}</div>

    <div class="section-title">User Accounts List</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Account Code</th>
                <th>Account Name</th>
                <th>Owner Type</th>
                <th>Owner</th>
                <th>Balance Type</th>
                <th class="text-right">Available Balance</th>
                <th class="text-right">Balance Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($accounts as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->accnt_code }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->owner_type }}</td>
                    <td>{{ $row->user->name ?? '-' }} ({{ $row->user->user_code ?? '-' }})</td>

                    @php
                        $balance = (float) $row->available_balance;
                        $type = $balance < 0 ? 'Collection' : ($balance > 0 ? 'Payable' : 'Zero');
                    @endphp

                    <td>{{ $type }}</td>
                    <td class="text-right {{ $balance < 0 ? 'danger' : ($balance > 0 ? 'primary' : '') }}">
                        {{ number_format($balance, 2) }}
                    </td>
                    <td class="text-right {{ $balance < 0 ? 'danger' : ($balance > 0 ? 'primary' : '') }}">
                        {{ number_format(abs($balance), 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No user accounts found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
