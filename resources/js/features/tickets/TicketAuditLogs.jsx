import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { LoadingSpinner, EmptyState } from '@/components/ui/LoadingSpinner';
import { formatDate } from '@/lib/utils';
import { ShieldCheck, User, Globe, Layers } from 'lucide-react';

export function TicketAuditLogs({ ticketId }) {
    const { data: logs, isLoading, isError, error } = useQuery({
        queryKey: queryKeys.tickets.auditLogs(ticketId),
        queryFn: async () => {
            const res = await apiClient.get(`/tickets/${ticketId}/audit-logs`);
            return res.data?.data || res.data;
        },
    });

    if (isLoading) {
        return <LoadingSpinner text="Fetching audit logs..." size="sm" />;
    }

    if (isError) {
        return (
            <div className="p-4 text-xs text-red-600 bg-red-50 dark:bg-red-950/30 rounded-lg">
                Failed to load audit logs: {error?.message}
            </div>
        );
    }

    if (!logs || logs.length === 0) {
        return (
            <EmptyState
                icon={ShieldCheck}
                title="No audit entries"
                description="Modifications to this ticket model are tracked and will appear here."
            />
        );
    }

    return (
        <div className="space-y-4">
            {logs.map((log) => {
                const hasChanges = (log.old_values && Object.keys(log.old_values).length > 0) ||
                                  (log.new_values && Object.keys(log.new_values).length > 0);

                return (
                    <div
                        key={log.id}
                        className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3"
                    >
                        <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-xs font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400 px-2 py-0.5 rounded border border-indigo-200 dark:border-indigo-800">
                                    {log.action}
                                </span>
                                <span className="text-xs text-slate-500">
                                    by <strong>{log.user?.name || `User #${log.user_id || 'System'}`}</strong>
                                </span>
                            </div>
                            <span className="text-xs text-slate-500">{formatDate(log.created_at)}</span>
                        </div>

                        {hasChanges ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                {log.old_values && Object.keys(log.old_values).length > 0 && (
                                    <div className="p-2.5 rounded-lg bg-red-50/50 dark:bg-red-950/20 border border-red-200/60 dark:border-red-900/40">
                                        <div className="font-semibold text-red-800 dark:text-red-300 mb-1">Old Values:</div>
                                        <pre className="font-mono text-[11px] text-slate-700 dark:text-slate-300 whitespace-pre-wrap overflow-x-auto">
                                            {JSON.stringify(log.old_values, null, 2)}
                                        </pre>
                                    </div>
                                )}

                                {log.new_values && Object.keys(log.new_values).length > 0 && (
                                    <div className="p-2.5 rounded-lg bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/40">
                                        <div className="font-semibold text-emerald-800 dark:text-emerald-300 mb-1">New Values:</div>
                                        <pre className="font-mono text-[11px] text-slate-700 dark:text-slate-300 whitespace-pre-wrap overflow-x-auto">
                                            {JSON.stringify(log.new_values, null, 2)}
                                        </pre>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="text-xs text-slate-500 italic">No attribute mutations logged.</div>
                        )}

                        {(log.ip_address || log.user_agent) && (
                            <div className="flex items-center gap-4 text-[10px] text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800/60">
                                {log.ip_address && (
                                    <span className="flex items-center gap-1">
                                        <Globe className="w-3 h-3" /> {log.ip_address}
                                    </span>
                                )}
                                {log.user_agent && (
                                    <span className="truncate max-w-sm" title={log.user_agent}>
                                        {log.user_agent}
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
