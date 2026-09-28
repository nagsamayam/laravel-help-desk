import React from 'react';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';
import { Loader2 } from 'lucide-react';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 cursor-pointer',
    {
        variants: {
            variant: {
                default: 'bg-indigo-600 text-white shadow hover:bg-indigo-700 focus-visible:ring-indigo-500',
                destructive: 'bg-red-600 text-white shadow-sm hover:bg-red-700 focus-visible:ring-red-500',
                outline: 'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800',
                secondary: 'bg-slate-100 text-slate-900 shadow-sm hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700',
                ghost: 'hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                link: 'text-indigo-600 underline-offset-4 hover:underline dark:text-indigo-400',
                success: 'bg-emerald-600 text-white shadow hover:bg-emerald-700 focus-visible:ring-emerald-500',
                amber: 'bg-amber-500 text-white shadow hover:bg-amber-600 focus-visible:ring-amber-500',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-8 rounded-md px-3 text-xs',
                lg: 'h-11 rounded-lg px-6 text-base',
                icon: 'h-9 w-9',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    }
);

export function Button({
    className,
    variant,
    size,
    loading = false,
    disabled = false,
    children,
    ...props
}) {
    return (
        <button
            className={cn(buttonVariants({ variant, size, className }))}
            disabled={disabled || loading}
            {...props}
        >
            {loading && <Loader2 className="w-4 h-4 animate-spin shrink-0" />}
            {children}
        </button>
    );
}
