import React from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export function LoadingSpinner({ size = 'md', className, text = 'Loading...' }) {
    const sizeMap = {
        sm: 'w-4 h-4',
        md: 'w-8 h-8',
        lg: 'w-12 h-12',
    };

    return (
        <div className={cn('flex flex-col items-center justify-center p-8 gap-3 text-slate-500', className)}>
            <Loader2 className={cn('animate-spin text-indigo-600 dark:text-indigo-400', sizeMap[size])} />
            {text && <p className="text-sm font-medium">{text}</p>}
        </div>
    );
}

export function EmptyState({ icon: Icon, title, description, action }) {
    return (
        <div className="flex flex-col items-center justify-center p-12 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 bg-white/50 dark:bg-slate-900/50">
            {Icon && (
                <div className="p-3 bg-slate-100 dark:bg-slate-800 rounded-full text-slate-500 mb-3">
                    <Icon className="w-8 h-8" />
                </div>
            )}
            <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100">{title}</h3>
            {description && <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-sm">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
