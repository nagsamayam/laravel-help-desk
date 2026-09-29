<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketMessage;
use App\Domain\Ticket\Models\TicketStatusHistory;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::all()->keyBy('name');
        $agents = User::where('role', Role::Agent)->get()->keyBy('email');
        $customers = User::where('role', Role::Customer)->get()->keyBy('email');
        $admin = User::where('role', Role::Admin)->first();

        if ($categories->isEmpty() || $customers->isEmpty() || $agents->isEmpty()) {
            return;
        }

        $bruce = $customers->get('bruce.wayne@example.com') ?? $customers->first();
        $john = $customers->get('john.customer@example.com') ?? $customers->first();
        $emily = $customers->get('emily.customer@example.com') ?? $customers->first();
        $sophia = $customers->get('sophia.customer@example.com') ?? $customers->first();
        $michael = $customers->get('michael.customer@example.com') ?? $customers->first();

        $sarah = $agents->get('sarah.agent@example.com') ?? $agents->first();
        $alex = $agents->get('alex.agent@example.com') ?? $agents->first();
        $david = $agents->get('david.agent@example.com') ?? $agents->first();
        $elena = $agents->get('elena.agent@example.com') ?? $agents->first();

        // 1. VIP Urgent Security Incident
        $ticket1 = Ticket::firstOrCreate(
            ['subject' => 'URGENT: Suspicious network intrusion detection in European datacenter'],
            [
                'description' => 'Our automated intrusion detection system triggered multiple high-severity alerts for unauthorized SSH brute-force attempts on European API endpoints.',
                'status' => TicketStatus::InProgess,
                'priority' => TicketPriority::Urgent,
                'category_id' => $categories->get('Security')?->id ?? $categories->first()->id,
                'customer_id' => $bruce->id,
                'assigned_to' => $david->id,
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(1),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket1->id, 'user_id' => $bruce->id],
            [
                'message' => 'Please investigate immediately. We have isolated subnet 192.168.40.0/24 as a precaution.',
                'is_internal' => false,
                'created_at' => now()->subHours(3),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket1->id, 'user_id' => $david->id, 'is_internal' => true],
            [
                'message' => 'Security Ops team is reviewing CloudFlare firewall logs and IP origin patterns. Initial vector looks like distributed botnet.',
                'is_internal' => true,
                'created_at' => now()->subHours(2),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket1->id, 'user_id' => $david->id, 'is_internal' => false],
            [
                'message' => 'Hello Bruce, we have activated our security incident protocol. Rate limiting rules and WAF IP geo-blocking have been applied.',
                'is_internal' => false,
                'created_at' => now()->subHours(1),
            ]
        );

        TicketStatusHistory::firstOrCreate(
            ['ticket_id' => $ticket1->id, 'to_status' => TicketStatus::InProgess],
            [
                'from_status' => TicketStatus::Open,
                'changed_by' => $david->id,
                'reason' => 'Assigned to security engineering team for immediate investigation.',
                'created_at' => now()->subHours(2),
            ]
        );

        AuditLog::firstOrCreate(
            ['auditable_type' => Ticket::class, 'auditable_id' => $ticket1->id, 'action' => 'status_changed'],
            [
                'user_id' => $david->id,
                'old_values' => ['status' => 'OPEN', 'assigned_to' => null],
                'new_values' => ['status' => 'IN_PROGRESS', 'assigned_to' => $david->id],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'created_at' => now()->subHours(2),
            ]
        );

        // 2. Billing & Invoice Overcharge Question
        $ticket2 = Ticket::firstOrCreate(
            ['subject' => 'Discrepancy in monthly subscription invoice #INV-2026-09'],
            [
                'description' => 'I noticed a charge for 15 additional team seats on our latest invoice, but our active team member count is only 8 seats.',
                'status' => TicketStatus::WaitingForCustomer,
                'priority' => TicketPriority::Medium,
                'category_id' => $categories->get('Billing')?->id ?? $categories->first()->id,
                'customer_id' => $emily->id,
                'assigned_to' => $alex->id,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subHours(5),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket2->id, 'user_id' => $emily->id],
            [
                'message' => 'Attached is our internal user roster for verification. Can you please check why the extra 7 seats were billed?',
                'is_internal' => false,
                'created_at' => now()->subDays(1),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket2->id, 'user_id' => $alex->id, 'is_internal' => false],
            [
                'message' => 'Hi Emily, thanks for reaching out. It looks like 7 invited pending seats were counted during billing snapshot. Could you confirm if you wish to revoke those pending invites?',
                'is_internal' => false,
                'created_at' => now()->subHours(5),
            ]
        );

        TicketStatusHistory::firstOrCreate(
            ['ticket_id' => $ticket2->id, 'to_status' => TicketStatus::WaitingForCustomer],
            [
                'from_status' => TicketStatus::InProgess,
                'changed_by' => $alex->id,
                'reason' => 'Waiting for customer confirmation regarding pending seat invitations.',
                'created_at' => now()->subHours(5),
            ]
        );

        // 3. Technical Unassigned Overdue Bug (Demonstrates Specification Filter & Strategy Routing)
        $ticket3 = Ticket::firstOrCreate(
            ['subject' => 'REST API returns 504 Gateway Timeout during bulk ticket export'],
            [
                'description' => 'When triggering CSV export for more than 5,000 tickets, the HTTP worker times out after 60 seconds with 504 Gateway Timeout.',
                'status' => TicketStatus::Open,
                'priority' => TicketPriority::High,
                'category_id' => $categories->get('Technical')?->id ?? $categories->first()->id,
                'customer_id' => $john->id,
                'assigned_to' => null,
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(4),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket3->id, 'user_id' => $john->id],
            [
                'message' => 'Steps to reproduce: POST /api/v1/tickets/export with filter { "date_range": "last_365_days" }',
                'is_internal' => false,
                'created_at' => now()->subDays(4),
            ]
        );

        // 4. Resolved Ticket with Notes
        $ticket4 = Ticket::firstOrCreate(
            ['subject' => 'Two-factor authentication (2FA) reset request for locked account'],
            [
                'description' => 'I replaced my mobile device and lost access to my Google Authenticator app for my primary work email.',
                'status' => TicketStatus::Resolved,
                'priority' => TicketPriority::High,
                'category_id' => $categories->get('Account')?->id ?? $categories->first()->id,
                'customer_id' => $sophia->id,
                'assigned_to' => $sarah->id,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subHours(6),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket4->id, 'user_id' => $sophia->id],
            [
                'message' => 'I have verified identity with government ID copy sent via secure portal link.',
                'is_internal' => false,
                'created_at' => now()->subDays(2),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket4->id, 'user_id' => $sarah->id, 'is_internal' => true],
            [
                'message' => 'Identity verified with security team approval token #SEC-9821. 2FA secret reset performed.',
                'is_internal' => true,
                'created_at' => now()->subHours(7),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket4->id, 'user_id' => $sarah->id, 'is_internal' => false],
            [
                'message' => 'Hi Sophia, your 2FA has been successfully reset. Please log in using the temporary one-time recovery link sent to your registered email.',
                'is_internal' => false,
                'created_at' => now()->subHours(6),
            ]
        );

        TicketStatusHistory::firstOrCreate(
            ['ticket_id' => $ticket4->id, 'to_status' => TicketStatus::Resolved],
            [
                'from_status' => TicketStatus::InProgess,
                'changed_by' => $sarah->id,
                'reason' => '2FA reset completed and verification confirmed by customer.',
                'created_at' => now()->subHours(6),
            ]
        );

        // 5. Closed General Inquiry
        $ticket5 = Ticket::firstOrCreate(
            ['subject' => 'Inquiry regarding custom SLA agreement for Enterprise plan'],
            [
                'description' => 'Does the Enterprise tier support 99.99% uptime guarantee and 15-minute response time SLA?',
                'status' => TicketStatus::Closed,
                'priority' => TicketPriority::Low,
                'category_id' => $categories->get('Sales')?->id ?? $categories->first()->id,
                'customer_id' => $michael->id,
                'assigned_to' => $elena->id,
                'created_at' => now()->subDays(6),
                'updated_at' => now()->subDays(1),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket5->id, 'user_id' => $michael->id],
            [
                'message' => 'We are evaluating your platform for our enterprise branch with 200+ agents.',
                'is_internal' => false,
                'created_at' => now()->subDays(6),
            ]
        );

        TicketMessage::firstOrCreate(
            ['ticket_id' => $ticket5->id, 'user_id' => $elena->id, 'is_internal' => false],
            [
                'message' => 'Hello Michael! Yes, our Enterprise tier includes a 99.99% SLA with dedicated technical account managers and 15-minute response guarantee. Our sales director has scheduled a call with your team.',
                'is_internal' => false,
                'created_at' => now()->subDays(5),
            ]
        );

        TicketStatusHistory::firstOrCreate(
            ['ticket_id' => $ticket5->id, 'to_status' => TicketStatus::Closed],
            [
                'from_status' => TicketStatus::Resolved,
                'changed_by' => $admin ? $admin->id : $elena->id,
                'reason' => 'Closed after customer confirmed satisfaction with sales consultation.',
                'created_at' => now()->subDays(1),
            ]
        );

        // 6. Additional diverse tickets for dashboard rich experience
        $additionalTickets = [
            [
                'subject' => 'Webhook delivery failure on customer.created event',
                'description' => 'Our webhook endpoint received 500 errors from endpoint https://hooks.client.io/sync during morning traffic spike.',
                'status' => TicketStatus::Open,
                'priority' => TicketPriority::Medium,
                'category_name' => 'Technical',
                'customer' => $john,
                'agent' => null,
            ],
            [
                'subject' => 'Update billing credit card and tax VAT ID',
                'description' => 'Need to update our European VAT registration number on future monthly recurring invoices.',
                'status' => TicketStatus::InProgess,
                'priority' => TicketPriority::Low,
                'category_name' => 'Billing',
                'customer' => $emily,
                'agent' => $alex,
            ],
            [
                'subject' => 'Feature Request: Export audit logs to Amazon S3 / Datadog',
                'description' => 'Would love to have automated daily streaming of audit logs into our enterprise SIEM platform.',
                'status' => TicketStatus::Open,
                'priority' => TicketPriority::Low,
                'category_name' => 'General',
                'customer' => $sophia,
                'agent' => null,
            ],
            [
                'subject' => 'SSO SAML 2.0 Integration with Okta failing on assertion',
                'description' => 'Okta SAML assertion returns invalid signature exception when users attempt Single Sign-On from dashboard.',
                'status' => TicketStatus::InProgess,
                'priority' => TicketPriority::High,
                'category_name' => 'Security',
                'customer' => $bruce,
                'agent' => $david,
            ],
        ];

        foreach ($additionalTickets as $item) {
            $cat = $categories->get($item['category_name']) ?? $categories->first();
            $t = Ticket::firstOrCreate(
                ['subject' => $item['subject']],
                [
                    'description' => $item['description'],
                    'status' => $item['status'],
                    'priority' => $item['priority'],
                    'category_id' => $cat->id,
                    'customer_id' => $item['customer']->id,
                    'assigned_to' => $item['agent']?->id,
                    'created_at' => now()->subHours(rand(4, 48)),
                    'updated_at' => now()->subHours(rand(1, 3)),
                ]
            );

            TicketMessage::firstOrCreate(
                ['ticket_id' => $t->id, 'user_id' => $item['customer']->id],
                [
                    'message' => $item['description'],
                    'is_internal' => false,
                    'created_at' => $t->created_at,
                ]
            );
        }
    }
}
