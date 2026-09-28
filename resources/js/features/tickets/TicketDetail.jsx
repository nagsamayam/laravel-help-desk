import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { useAuthStore } from '@/stores/auth-store';
import { StatusBadge, PriorityBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/Tabs';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';
import { formatDate } from '@/lib/utils';
import { ArrowLeft, User, Calendar, GitFork, UserCheck, RefreshCw, MessageSquare, History, ShieldCheck, Clock } from 'lucide-react';
import { TicketMessages } from './TicketMessages';
import { TicketStatusHistoryTimeline } from './TicketStatusHistoryTimeline';
import { TicketAuditLogs } from './TicketAuditLogs';
import { TicketStateModal } from './TicketStateModal';
import { TicketAssignModal } from './TicketAssignModal';
import { TicketRoutingModal } from './TicketRoutingModal';

export function TicketDetail({ ticketId, onBack }) {
    const { isAgent, isAdmin } = useAuthStore();
    const [isStateModalOpen, setIsStateModalOpen] = useState(false);
    const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
    const [isRoutingModalOpen, setIsRoutingModalOpen] = useState(false);

    const { data: ticket, isLoading, isError, error } = useQuery({
        queryKey: queryKeys.tickets.detail(ticketId),
        queryFn: async () => {
            const res = await apiClient.get(`/tickets/${ticketId}`);
            return res.data?.data || res.data;
        },
    });

    if (isLoading) {
        return <LoadingSpinner text="Loading ticket details..." size="lg" />;
    }

    if (isError) {
        return (
            <div className="p-8 text-center text-red-600 bg-red-50 dark:bg-red-950/20 rounded-xl">
                Failed to load ticket #{ticketId}: {error?.message}
                <div className="mt-4">
                    <Button variant="outline" onClick={onBack}>Go Back</Button>
                </div>
            </div>
        );
    }

    if (!ticket) return null;

    const isOverdue = ticket.sla_due_at && new Date(ticket.sla_due_at) < new Date() && ticket.status !== 'closed' && ticket.status !== 'resolved';

    return (
        <div className="space-y-6">
            {/* Top Navigation & Action Controls */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="sm" onClick={onBack} className="gap-1.5">
                        <ArrowLeft className="w-4 h-4" />
                        <span>Back</span>
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="font-mono text-sm font-bold text-slate-500">#{ticket.id}</span>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-slate-100">{ticket.subject || ticket.title}</h1>
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setIsStateModalOpen(true)}
                        className="gap-1.5"
                    >
                        <RefreshCw className="w-3.5 h-3.5" />
                        <span>Transition State</span>
                    </Button>

                    {isAgent() && (
                        <>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setIsAssignModalOpen(true)}
                                className="gap-1.5"
                            >
                                <UserCheck className="w-3.5 h-3.5" />
                                <span>Assign Agent</span>
                            </Button>

                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => setIsRoutingModalOpen(true)}
                                className="gap-1.5"
                            >
                                <GitFork className="w-3.5 h-3.5" />
                                <span>Evaluate Routing</span>
                            </Button>
                        </>
                    )}
                </div>
            </div>

            {/* Ticket Info Card Grid */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs">
                <div>
                    <span className="text-slate-400 font-medium block mb-1">Status</span>
                    <StatusBadge status={ticket.status} />
                </div>

                <div>
                    <span className="text-slate-400 font-medium block mb-1">Priority</span>
                    <PriorityBadge priority={ticket.priority} />
                </div>

                <div>
                    <span className="text-slate-400 font-medium block mb-1">Assigned To</span>
                    <div className="font-semibold text-slate-800 dark:text-slate-200">
                        {ticket.assigned_to_user?.name || ticket.assigned_agent?.name || 'Unassigned'}
                    </div>
                </div>

                <div>
                    <span className="text-slate-400 font-medium block mb-1">Category & Customer</span>
                    <div className="font-semibold text-slate-800 dark:text-slate-200">
                        {ticket.category?.name || 'General Support'}
                    </div>
                    <div className="text-slate-500 text-[11px] mt-0.5">
                        by {ticket.customer?.name || `Customer #${ticket.customer_id}`}
                    </div>
                </div>
            </div>

            {/* Description Card */}
            <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                <h3 className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Description</h3>
                <p className="text-sm text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed">
                    {ticket.description}
                </p>
                <div className="flex items-center gap-4 mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400">
                    <span className="flex items-center gap-1">
                        <Calendar className="w-3.5 h-3.5" /> Created {formatDate(ticket.created_at)}
                    </span>
                    {ticket.sla_due_at && (
                        <span className={`flex items-center gap-1 font-medium ${isOverdue ? 'text-red-500 font-bold' : ''}`}>
                            <Clock className="w-3.5 h-3.5" /> SLA Due: {formatDate(ticket.sla_due_at)}
                        </span>
                    )}
                </div>
            </div>

            {/* Tabs Section: Discussion, Status History, Audit Logs */}
            <Tabs defaultValue="discussion" className="w-full">
                <TabsList className="grid w-full grid-cols-3 max-w-md">
                    <TabsTrigger value="discussion" className="gap-1.5">
                        <MessageSquare className="w-3.5 h-3.5" />
                        <span>Discussion</span>
                    </TabsTrigger>
                    <TabsTrigger value="history" className="gap-1.5">
                        <History className="w-3.5 h-3.5" />
                        <span>Status History</span>
                    </TabsTrigger>
                    <TabsTrigger value="audit" className="gap-1.5">
                        <ShieldCheck className="w-3.5 h-3.5" />
                        <span>Audit Trail</span>
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="discussion">
                    <TicketMessages ticketId={ticket.id} />
                </TabsContent>

                <TabsContent value="history">
                    <TicketStatusHistoryTimeline ticketId={ticket.id} />
                </TabsContent>

                <TabsContent value="audit">
                    <TicketAuditLogs ticketId={ticket.id} />
                </TabsContent>
            </Tabs>

            {/* Modals */}
            <TicketStateModal
                isOpen={isStateModalOpen}
                onClose={() => setIsStateModalOpen(false)}
                ticket={ticket}
            />

            <TicketAssignModal
                isOpen={isAssignModalOpen}
                onClose={() => setIsAssignModalOpen(false)}
                ticket={ticket}
            />

            <TicketRoutingModal
                isOpen={isRoutingModalOpen}
                onClose={() => setIsRoutingModalOpen(false)}
                ticket={ticket}
            />
        </div>
    );
}
