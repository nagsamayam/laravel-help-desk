import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/Table';
import { Input, Select } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { LoadingSpinner, EmptyState } from '@/components/ui/LoadingSpinner';
import { formatDate } from '@/lib/utils';
import { Activity, ShieldCheck, ChevronLeft, ChevronRight, RotateCcw } from 'lucide-react';

export function AuditLogList() {
    const [action, setAction] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading, isError, error } = useQuery({
        queryKey: queryKeys.auditLogs.list({ action, page }),
        queryFn: async () => {
            const params = new URLSearchParams();
            if (action) params.append('action', action);
            if (page) params.append('page', page);

            const res = await apiClient.get(`/audit-logs?${params.toString()}`);
            return res.data;
        },
    });

    const logs = data?.data || [];
    const meta = data?.meta || {};

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
                <div>
                    <h1 className="text-xl font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <Activity className="w-5 h-5 text-indigo-600" />
                        System Audit Trail
                    </h1>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Track domain state updates, attribute changes, and administrative actions.
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <Select value={action} onChange={(e) => { setAction(e.target.value); setPage(1); }}>
                        <option value="">All Actions</option>
                        <option value="created">Created</option>
                        <option value="updated">Updated</option>
                        <option value="deleted">Deleted</option>
                        <option value="status_changed">Status Changed</option>
                    </Select>

                    {action && (
                        <Button variant="outline" size="sm" onClick={() => { setAction(''); setPage(1); }}>
                            <RotateCcw className="w-4 h-4" />
                        </Button>
                    )}
                </div>
            </div>

            {isLoading ? (
                <LoadingSpinner text="Fetching audit trail..." size="lg" />
            ) : isError ? (
                <div className="p-6 text-center text-red-600 bg-red-50 dark:bg-red-950/20 rounded-xl">
                    Failed to load audit logs: {error?.message}
                </div>
            ) : logs.length === 0 ? (
                <EmptyState
                    icon={ShieldCheck}
                    title="No audit entries"
                    description="No audit logs matched your search filters."
                />
            ) : (
                <div className="space-y-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-20">ID</TableHead>
                                <TableHead className="w-28">Action</TableHead>
                                <TableHead className="w-48">Entity Target</TableHead>
                                <TableHead className="w-44">User</TableHead>
                                <TableHead>Changes Recorded</TableHead>
                                <TableHead className="w-36 text-right">Timestamp</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.map((log) => {
                                const oldKeys = log.old_values ? Object.keys(log.old_values) : [];
                                const newKeys = log.new_values ? Object.keys(log.new_values) : [];
                                const changedKeys = Array.from(new Set([...oldKeys, ...newKeys]));

                                return (
                                    <TableRow key={log.id}>
                                        <TableCell className="font-mono text-xs text-slate-500">#{log.id}</TableCell>
                                        <TableCell>
                                            <span className="font-mono text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400 px-2 py-0.5 rounded border border-indigo-200 dark:border-indigo-800">
                                                {log.action}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-xs font-mono text-slate-700 dark:text-slate-300">
                                            {log.auditable_type?.split('\\').pop()} #{log.auditable_id}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            <div className="font-medium text-slate-900 dark:text-slate-100">
                                                {log.user?.name || `User #${log.user_id || 'System'}`}
                                            </div>
                                            {log.ip_address && (
                                                <div className="text-[10px] text-slate-400">{log.ip_address}</div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {changedKeys.length > 0 ? (
                                                <div className="flex flex-wrap gap-1">
                                                    {changedKeys.map((k) => (
                                                        <span key={k} className="font-mono text-[10px] bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-slate-700 dark:text-slate-300">
                                                            {k}
                                                        </span>
                                                    ))}
                                                </div>
                                            ) : (
                                                <span className="text-slate-400 italic">Snapshot record</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right text-xs text-slate-500">
                                            {formatDate(log.created_at)}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>

                    {meta.last_page > 1 && (
                        <div className="flex items-center justify-between px-2 pt-2 text-xs text-slate-500">
                            <div>
                                Page <span className="font-semibold text-slate-700 dark:text-slate-300">{meta.current_page}</span> of{' '}
                                <span className="font-semibold text-slate-700 dark:text-slate-300">{meta.last_page}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={meta.current_page <= 1}
                                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                                >
                                    <ChevronLeft className="w-4 h-4 mr-1" /> Prev
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={meta.current_page >= meta.last_page}
                                    onClick={() => setPage((p) => p + 1)}
                                >
                                    Next <ChevronRight className="w-4 h-4 ml-1" />
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
