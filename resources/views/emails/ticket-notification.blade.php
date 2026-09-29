<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f8;
            color: #334155;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .wrapper {
            width: 100%;
            background-color: #f4f6f8;
            padding: 30px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }
        .header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 24px 32px;
            text-align: left;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -0.025em;
        }
        .content {
            padding: 32px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #0f172a;
        }
        .message-box {
            background-color: #f8fafc;
            border-left: 4px solid #3b82f6;
            padding: 16px 20px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 15px;
            color: #1e293b;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 24px 0;
            font-size: 14px;
        }
        .details-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .details-table td.label {
            font-weight: 600;
            color: #64748b;
            width: 35%;
        }
        .details-table td.value {
            color: #0f172a;
            font-weight: 500;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge-open { background-color: #dbeafe; color: #1e40af; }
        .badge-inprogress { background-color: #fef3c7; color: #92400e; }
        .badge-resolved { background-color: #dcfce7; color: #166534; }
        .badge-closed { background-color: #f1f5f9; color: #475569; }
        .badge-urgent { background-color: #fee2e2; color: #991b1b; }
        .btn-container {
            margin-top: 32px;
            text-align: center;
        }
        .btn {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            padding: 12px 28px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 32px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>Help Desk Support</h1>
            </div>
            <div class="content">
                <div class="greeting">
                    Hello {{ $recipient->first_name ?? $recipient->name ?? 'there' }},
                </div>

                <p style="margin: 0 0 16px 0; color: #475569; font-size: 15px;">
                    {{ $title }}
                </p>

                <div class="message-box">
                    {!! nl2br(e($contentMessage)) !!}
                </div>

                @if(!empty($context['ticket_id']) || $ticket)
                    <table class="details-table">
                        @if($ticket)
                            <tr>
                                <td class="label">Ticket Subject</td>
                                <td class="value">{{ $ticket->subject }}</td>
                            </tr>
                            <tr>
                                <td class="label">Ticket #</td>
                                <td class="value">#{{ $ticket->id }}</td>
                            </tr>
                            <tr>
                                <td class="label">Priority</td>
                                <td class="value">{{ $ticket->priority?->value ?? 'MEDIUM' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Status</td>
                                <td class="value">{{ $ticket->status?->value ?? 'OPEN' }}</td>
                            </tr>
                        @elseif(!empty($context['ticket_id']))
                            <tr>
                                <td class="label">Ticket ID</td>
                                <td class="value">#{{ $context['ticket_id'] }}</td>
                            </tr>
                            @if(!empty($context['status']))
                                <tr>
                                    <td class="label">Status</td>
                                    <td class="value">{{ $context['status'] }}</td>
                                </tr>
                            @endif
                        @endif
                    </table>
                @endif

                <div class="btn-container">
                    @php
                        $ticketId = $ticket->id ?? $context['ticket_id'] ?? null;
                        $targetUrl = $ticketId ? "{$appUrl}/tickets/{$ticketId}" : $appUrl;
                    @endphp
                    <a href="{{ $targetUrl }}" class="btn" target="_blank">
                        View in Help Desk
                    </a>
                </div>
            </div>
            <div class="footer">
                <p style="margin: 0;">This is an automated notification from {{ config('app.name', 'Help Desk') }}.</p>
                <p style="margin: 4px 0 0 0;">Please do not reply directly to this automated email.</p>
            </div>
        </div>
    </div>
</body>
</html>
