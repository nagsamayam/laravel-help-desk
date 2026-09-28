import React, { createContext, useContext, useState } from 'react';
import { cn } from '@/lib/utils';

const TabsContext = createContext(null);

export function Tabs({ defaultValue, value, onValueChange, className, children }) {
    const [selectedTab, setSelectedTab] = useState(defaultValue);
    const activeValue = value !== undefined ? value : selectedTab;

    const changeTab = (val) => {
        if (value === undefined) setSelectedTab(val);
        onValueChange?.(val);
    };

    return (
        <TabsContext.Provider value={{ activeValue, changeTab }}>
            <div className={cn('w-full', className)}>{children}</div>
        </TabsContext.Provider>
    );
}

export function TabsList({ className, children }) {
    return (
        <div
            className={cn(
                'inline-flex h-10 items-center justify-center rounded-lg bg-slate-100 p-1 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                className
            )}
        >
            {children}
        </div>
    );
}

export function TabsTrigger({ value, className, children }) {
    const { activeValue, changeTab } = useContext(TabsContext);
    const isActive = activeValue === value;

    return (
        <button
            type="button"
            onClick={() => changeTab(value)}
            className={cn(
                'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:pointer-events-none disabled:opacity-50 cursor-pointer',
                isActive
                    ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-slate-100'
                    : 'hover:text-slate-900 dark:hover:text-slate-100',
                className
            )}
        >
            {children}
        </button>
    );
}

export function TabsContent({ value, className, children }) {
    const { activeValue } = useContext(TabsContext);
    if (activeValue !== value) return null;

    return (
        <div className={cn('mt-4 focus-visible:outline-none', className)}>
            {children}
        </div>
    );
}
