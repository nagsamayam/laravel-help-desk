import React from 'react';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold transition-colors shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800',
                secondary: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700',
                success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
                warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
                destructive: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300 border border-red-200 dark:border-red-800',
                outline: 'border border-slate-300 text-slate-700 dark:border-slate-700 dark:text-slate-300',
                blue: 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300 border border-sky-200 dark:border-sky-800',
                purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    }
);

export function Badge({ className, variant, ...props }) {
    return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export function StatusBadge({ status }) {
    const normalized = (status || '').toUpperCase();
    const statusMap = {
        OPEN: { label: 'Open', variant: 'blue' },
        IN_PROGRESS: { label: 'In Progress', variant: 'warning' },
        WAITING_FOR_CUSTOMER: { label: 'Waiting for Customer', variant: 'purple' },
        RESOLVED: { label: 'Resolved', variant: 'success' },
        CLOSED: { label: 'Closed', variant: 'secondary' },
    };

    const config = statusMap[normalized] || { label: status, variant: 'secondary' };

    return <Badge variant={config.variant}>{config.label}</Badge>;
}

export function PriorityBadge({ priority }) {
    const normalized = (priority || '').toUpperCase();
    const priorityMap = {
        LOW: { label: 'Low', variant: 'secondary' },
        MEDIUM: { label: 'Medium', variant: 'blue' },
        HIGH: { label: 'High', variant: 'warning' },
        URGENT: { label: 'Urgent', variant: 'destructive' },
    };

    const config = priorityMap[normalized] || { label: priority, variant: 'secondary' };

    return <Badge variant={config.variant}>{config.label}</Badge>;
}
