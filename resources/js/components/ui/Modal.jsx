import React, { useEffect } from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

export function Modal({ isOpen, onClose, title, description, children, maxWidth = 'max-w-lg' }) {
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in duration-200">
            <div
                className="fixed inset-0"
                onClick={onClose}
                aria-hidden="true"
            />
            <div
                className={cn(
                    'relative w-full rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 z-10 max-h-[90vh] overflow-y-auto transform transition-all',
                    maxWidth
                )}
            >
                <div className="flex items-start justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        {title && <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{title}</h3>}
                        {description && <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300 transition-colors"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="pt-4">{children}</div>
            </div>
        </div>
    );
}
