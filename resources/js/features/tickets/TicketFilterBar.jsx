import React from 'react';
import { Input, Select } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Search, RotateCcw, Flame, Clock, UserX } from 'lucide-react';
import { cn } from '@/lib/utils';

export function TicketFilterBar({ filters, onFilterChange, onReset }) {
    const handleQuickFilter = (key, value) => {
        onFilterChange({
            ...filters,
            [key]: filters[key] === value ? '' : value,
        });
    };

    return (
        <div className="space-y-3 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                <div className="relative">
                    <Input
                        placeholder="Search tickets..."
                        value={filters.search || ''}
                        onChange={(e) => onFilterChange({ ...filters, search: e.target.value })}
                        className="pl-9"
                    />
                    <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                </div>

                <Select
                    value={filters.status || ''}
                    onChange={(e) => onFilterChange({ ...filters, status: e.target.value })}
                >
                    <option value="">All Statuses</option>
                    <option value="OPEN">Open</option>
                    <option value="IN_PROGRESS">In Progress</option>
                    <option value="WAITING_FOR_CUSTOMER">Waiting for Customer</option>
                    <option value="RESOLVED">Resolved</option>
                    <option value="CLOSED">Closed</option>
                </Select>

                <Select
                    value={filters.priority || ''}
                    onChange={(e) => onFilterChange({ ...filters, priority: e.target.value })}
                >
                    <option value="">All Priorities</option>
                    <option value="LOW">Low</option>
                    <option value="MEDIUM">Medium</option>
                    <option value="HIGH">High</option>
                    <option value="URGENT">Urgent</option>
                </Select>

                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="default"
                        onClick={onReset}
                        className="w-full gap-2 text-slate-600 dark:text-slate-400"
                    >
                        <RotateCcw className="w-4 h-4" />
                        <span>Reset</span>
                    </Button>
                </div>
            </div>

            {/* Quick Specification Toggle Pills */}
            <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                <span className="text-slate-500 font-medium">Specification Filters:</span>

                <button
                    type="button"
                    onClick={() => handleQuickFilter('urgent', '1')}
                    className={cn(
                        'inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-xs font-medium transition-all cursor-pointer',
                        filters.urgent === '1'
                            ? 'bg-red-50 text-red-700 border-red-300 dark:bg-red-950/50 dark:text-red-400 dark:border-red-800'
                            : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800'
                    )}
                >
                    <Flame className="w-3.5 h-3.5 text-red-500" />
                    <span>Urgent Only</span>
                </button>

                <button
                    type="button"
                    onClick={() => handleQuickFilter('overdue', '1')}
                    className={cn(
                        'inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-xs font-medium transition-all cursor-pointer',
                        filters.overdue === '1'
                            ? 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800'
                            : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800'
                    )}
                >
                    <Clock className="w-3.5 h-3.5 text-amber-500" />
                    <span>Overdue SLA</span>
                </button>

                <button
                    type="button"
                    onClick={() => handleQuickFilter('unassigned', '1')}
                    className={cn(
                        'inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-xs font-medium transition-all cursor-pointer',
                        filters.unassigned === '1'
                            ? 'bg-indigo-50 text-indigo-700 border-indigo-300 dark:bg-indigo-950/50 dark:text-indigo-400 dark:border-indigo-800'
                            : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800'
                    )}
                >
                    <UserX className="w-3.5 h-3.5 text-indigo-500" />
                    <span>Unassigned</span>
                </button>
            </div>
        </div>
    );
}
