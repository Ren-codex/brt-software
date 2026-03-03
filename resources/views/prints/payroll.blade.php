<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll - {{ $payroll->payroll_no }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            font-size: 14px;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .info-box {
            width: 48%;
        }
        .info-box h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Payroll Report</h1>
        <p>Payroll No: {{ $payroll->payroll_no }}</p>
        <p>Pay Period: {{ $payroll->pay_period_start->format('M d, Y') }} - {{ $payroll->pay_period_end->format('M d, Y') }}</p>
    </div>

    <div class="info-section">
        <div class="info-box">
            <h3>Payroll Information</h3>
            <p><strong>Status:</strong> {{ ucfirst($payroll->status->slug) }}</p>
            <p><strong>Template:</strong> {{ $payroll->template ? $payroll->template->name : 'N/A' }}</p>
            <p><strong>Created By:</strong> {{ $payroll->creator ? $payroll->creator->name : 'N/A' }}</p>
            <p><strong>Created Date:</strong> {{ $payroll->created_at->format('M d, Y H:i') }}</p>
        </div>
        <div class="info-box">
            <h3>Summary</h3>
            <p><strong>Total Employees:</strong> {{ $items->count() }}</p>
            <p><strong>Total Net Salary:</strong> PHP {{ number_format($payroll->total_amount, 2) }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee Name</th>
                <th>Basic Salary</th>
                <th>Overtime Hours</th>
                <th>Overtime Rate</th>
                <th>Overtime Pay</th>
                <th>Deductions</th>
                <th>Net Salary</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->employee ? $item->employee->full_name : 'N/A' }}</td>
                <td>{{ number_format($item->basic_salary ?? 0, 2) }}</td>
                <td>{{ $item->overtime_hours ?? 0 }}</td>
                <td>{{ number_format($item->overtime_rate ?? 0, 2) }}</td>
                <td>{{ number_format(($item->overtime_hours ?? 0) * ($item->overtime_rate ?? 0), 2) }}</td>
                <td>{{ number_format($item->deductions ?? 0, 2) }}</td>
                <td>{{ number_format($item->net_salary ?? 0, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="7" style="text-align: right;">Total Net Salary:</td>
                <td>{{ number_format($payroll->total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Generated on {{ now()->format('M d, Y H:i') }}</p>
    </div>
</body>
</html>
