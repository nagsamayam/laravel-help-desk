import React from 'react';
import { cn } from '@/lib/utils';

export const Input = React.forwardRef(({ className, type = 'text', error, ...props }, ref) => {
    return (
        <div className="w-full">
            <input
                type={type}
                className={cn(
                    'flex h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-indigo-400',
                    error && 'border-red-500 focus:ring-red-500 focus:border-red-500 dark:border-red-500',
                    className
                )}
                ref={ref}
                {...props}
            />
            {error && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
});
Input.displayName = 'Input';

export const Textarea = React.forwardRef(({ className, error, rows = 3, ...props }, ref) => {
    return (
        <div className="w-full">
            <textarea
                rows={rows}
                className={cn(
                    'flex w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-indigo-400',
                    error && 'border-red-500 focus:ring-red-500 focus:border-red-500 dark:border-red-500',
                    className
                )}
                ref={ref}
                {...props}
            />
            {error && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
});
Textarea.displayName = 'Textarea';

export const Select = React.forwardRef(({ className, error, children, ...props }, ref) => {
    return (
        <div className="w-full">
            <select
                className={cn(
                    'flex h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:ring-indigo-400',
                    error && 'border-red-500 focus:ring-red-500 focus:border-red-500 dark:border-red-500',
                    className
                )}
                ref={ref}
                {...props}
            >
                {children}
            </select>
            {error && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
});
Select.displayName = 'Select';
