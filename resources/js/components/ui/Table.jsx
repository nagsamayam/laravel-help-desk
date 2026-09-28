import React from 'react';
import { cn } from '@/lib/utils';

export function Table({ className, ...props }) {
    return (
        <div className="relative w-full overflow-auto rounded-xl border border-slate-200 dark:border-slate-800">
            <table className={cn('w-full caption-bottom text-sm text-left', className)} {...props} />
        </div>
    );
}

export function TableHeader({ className, ...props }) {
    return <thead className={cn('bg-slate-50/80 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-500 uppercase tracking-wider', className)} {...props} />;
}

export function TableBody({ className, ...props }) {
    return <tbody className={cn('divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950', className)} {...props} />;
}

export function TableRow({ className, ...props }) {
    return (
        <tr
            className={cn(
                'transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-900/50',
                className
            )}
            {...props}
        />
    );
}

export function TableHead({ className, ...props }) {
    return <th className={cn('h-10 px-4 text-left align-middle font-medium text-slate-600 dark:text-slate-400', className)} {...props} />;
}

export function TableCell({ className, ...props }) {
    return <td className={cn('p-4 align-middle text-slate-700 dark:text-slate-300', className)} {...props} />;
}
