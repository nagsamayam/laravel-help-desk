import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { StatusBadge } from '@/components/ui/Badge';
import { LoadingSpinner, EmptyState } from '@/components/ui/LoadingSpinner';
import { formatDate } from '@/lib/utils';
import { History, ArrowRight, User, CheckCircle2 } from 'lucide-react';

export function TicketStatusHistoryTimeline({ ticketId }) {
    const { data: histories, isLoading, isError, error } = useQuery({
        queryKey: queryKeys.tickets.history(ticketId),
        queryFn: async () => {
            const res = await apiClient.get(`/tickets/${ticketId}/history`);
            return res.data?.data || res.data;
        },
    });

    if (isLoading) {
        return <LoadingSpinner text="Loading status progression..." size="sm" />;
    }

    if (isError) {
        return (
            <div className="p-4 text-xs text-red-600 bg-red-50 dark:bg-red-950/30 rounded-lg">
                Failed to load status history: {error?.message}
            </div>
        );
    }

    if (!histories || histories.length === 0) {
        return (
            <EmptyState
                icon={History}
                title="No status history"
                description="State changes and lifecycle progressions will be recorded here."
            />
        );
    }

    return (
        <div className="space-y-4">
            <div className="relative pl-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800 space-y-6">
                {histories.map((entry, index) => {
                    return (
                        <div key={entry.id || index} className="relative group">
                            <div className="absolute -left-6 top-1 w-5 h-5 rounded-full bg-white dark:bg-slate-900 border-2 border-indigo-600 flex items-center justify-center">
                                <div className="w-1.5 h-1.5 rounded-full bg-indigo-600" />
                            </div>

                            <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {entry.from_status ? (
                                            <>
                                                <StatusBadge status={entry.from_status} />
                                                <ArrowRight className="w-3.5 h-3.5 text-slate-400" />
                                            </>
                                        ) : (
                                            <span className="text-xs font-semibold text-slate-500">Initial State:</span>
                                        )}
                                        <StatusBadge status={entry.to_status} />
                                    </div>

                                    <span className="text-xs text-slate-500">{formatDate(entry.created_at)}</span>
                                </div>

                                {entry.reason && (
                                    <p className="text-xs text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800">
                                        <span className="font-semibold text-slate-900 dark:text-slate-100">Reason: </span>
                                        {entry.reason}
                                    </p>
                                )}

                                <div className="flex items-center gap-1.5 text-[11px] text-slate-500">
                                    <User className="w-3.5 h-3.5" />
                                    <span>Changed by: <strong>{entry.changed_by_user?.name || entry.user?.name || `User #${entry.changed_by || 'System'}`}</strong></span>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
