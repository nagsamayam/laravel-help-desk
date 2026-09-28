import React from 'react';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/Table';
import { StatusBadge, PriorityBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { LoadingSpinner, EmptyState } from '@/components/ui/LoadingSpinner';
import { formatRelativeTime } from '@/lib/utils';
import { useAuthStore } from '@/stores/auth-store';
import { Ticket, ChevronLeft, ChevronRight, UserCheck, AlertCircle, Clock } from 'lucide-react';

export function TicketList({
    tickets,
    pagination,
    isLoading,
    isError,
    error,
    onSelectTicket,
    onAssignTicket,
    onPageChange,
}) {
    const { isAgent } = useAuthStore();

    if (isLoading) {
        return <LoadingSpinner text="Fetching tickets..." size="lg" />;
    }

    if (isError) {
        return (
            <div className="p-6 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/50 dark:bg-red-950/20 text-center">
                <AlertCircle className="w-8 h-8 text-red-500 mx-auto mb-2" />
                <h3 className="text-sm font-semibold text-red-800 dark:text-red-300">Failed to load tickets</h3>
                <p className="text-xs text-red-600 dark:text-red-400 mt-1">{error?.message || 'Server error'}</p>
            </div>
        );
    }

    if (!tickets || tickets.length === 0) {
        return (
            <EmptyState
                icon={Ticket}
                title="No tickets found"
                description="Try adjusting your filters or create a new ticket to get started."
            />
        );
    }

    return (
        <div className="space-y-4">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-16">ID</TableHead>
                        <TableHead>Subject & Category</TableHead>
                        <TableHead className="w-32">Status</TableHead>
                        <TableHead className="w-28">Priority</TableHead>
                        <TableHead className="w-44">Assignee</TableHead>
                        <TableHead className="w-32 text-right">Created</TableHead>
                        {isAgent() && <TableHead className="w-24 text-right">Action</TableHead>}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {tickets.map((ticket) => {
                        const isOverdue = ticket.sla_due_at && new Date(ticket.sla_due_at) < new Date() && ticket.status !== 'closed' && ticket.status !== 'resolved';

                        return (
                            <TableRow
                                key={ticket.id}
                                onClick={() => onSelectTicket(ticket.id)}
                                className="cursor-pointer"
                            >
                                <TableCell className="font-mono text-xs text-slate-500">
                                    #{ticket.id}
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-col">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-slate-900 dark:text-slate-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                {ticket.subject || ticket.title}
                                            </span>
                                            {isOverdue && (
                                                <span className="inline-flex items-center gap-1 text-[10px] font-bold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/50 px-1.5 py-0.5 rounded border border-red-200 dark:border-red-800">
                                                    <Clock className="w-3 h-3" /> SLA
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2 mt-0.5 text-xs text-slate-500">
                                            <span>{ticket.category?.name || 'General Support'}</span>
                                            {ticket.customer && (
                                                <>
                                                    <span>•</span>
                                                    <span>by {ticket.customer.name}</span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <StatusBadge status={ticket.status} />
                                </TableCell>
                                <TableCell>
                                    <PriorityBadge priority={ticket.priority} />
                                </TableCell>
                                <TableCell>
                                    {ticket.assigned_to_user || ticket.assigned_agent ? (
                                        <div className="flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300">
                                            <div className="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-[10px] font-bold">
                                                {(ticket.assigned_to_user?.name || ticket.assigned_agent?.name || 'A')[0]}
                                            </div>
                                            <span className="truncate max-w-[120px]">
                                                {ticket.assigned_to_user?.name || ticket.assigned_agent?.name}
                                            </span>
                                        </div>
                                    ) : (
                                        <span className="text-xs text-slate-400 italic">Unassigned</span>
                                    )}
                                </TableCell>
                                <TableCell className="text-right text-xs text-slate-500">
                                    {formatRelativeTime(ticket.created_at)}
                                </TableCell>
                                {isAgent() && (
                                    <TableCell className="text-right" onClick={(e) => e.stopPropagation()}>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                if (onAssignTicket) onAssignTicket(ticket);
                                            }}
                                            className="h-7 px-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/50"
                                        >
                                            <UserCheck className="w-3.5 h-3.5 mr-1" />
                                            <span>{ticket.assigned_to ? 'Reassign' : 'Assign'}</span>
                                        </Button>
                                    </TableCell>
                                )}
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>

            {/* Pagination Controls */}
            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-between px-2 pt-2 text-xs text-slate-500">
                    <div>
                        Showing page <span className="font-semibold text-slate-700 dark:text-slate-300">{pagination.current_page}</span> of{' '}
                        <span className="font-semibold text-slate-700 dark:text-slate-300">{pagination.last_page}</span> ({pagination.total} total)
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={pagination.current_page <= 1}
                            onClick={() => onPageChange(pagination.current_page - 1)}
                            className="gap-1"
                        >
                            <ChevronLeft className="w-4 h-4" />
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={pagination.current_page >= pagination.last_page}
                            onClick={() => onPageChange(pagination.current_page + 1)}
                            className="gap-1"
                        >
                            Next
                            <ChevronRight className="w-4 h-4" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
