import React from 'react';
import { useUiStore } from '@/stores/ui-store';
import { CheckCircle2, AlertTriangle, AlertCircle, Info, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export function ToastContainer() {
    const { toasts, removeToast } = useUiStore();

    if (toasts.length === 0) return null;

    const iconMap = {
        success: <CheckCircle2 className="w-5 h-5 text-emerald-500 shrink-0" />,
        warning: <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0" />,
        error: <AlertCircle className="w-5 h-5 text-red-500 shrink-0" />,
        info: <Info className="w-5 h-5 text-indigo-500 shrink-0" />,
    };

    const bgMap = {
        success: 'border-emerald-200 dark:border-emerald-800 bg-white dark:bg-slate-900',
        warning: 'border-amber-200 dark:border-amber-800 bg-white dark:bg-slate-900',
        error: 'border-red-200 dark:border-red-800 bg-white dark:bg-slate-900',
        info: 'border-indigo-200 dark:border-indigo-800 bg-white dark:bg-slate-900',
    };

    return (
        <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    className={cn(
                        'pointer-events-auto flex items-start gap-3 p-4 rounded-xl border shadow-lg transition-all animate-in slide-in-from-bottom-5',
                        bgMap[toast.type] || bgMap.info
                    )}
                >
                    {iconMap[toast.type] || iconMap.info}
                    <div className="flex-1 min-w-0">
                        {toast.title && (
                            <h4 className="text-sm font-semibold text-slate-900 dark:text-slate-100">{toast.title}</h4>
                        )}
                        {toast.message && (
                            <p className="text-xs text-slate-600 dark:text-slate-400 mt-0.5">{toast.message}</p>
                        )}
                    </div>
                    <button
                        onClick={() => removeToast(toast.id)}
                        className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>
            ))}
        </div>
    );
}
