import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

export function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
    }).format(date);
}

export function formatRelativeTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
    return formatDate(dateString);
}

export function extractApiErrors(err) {
    const data = err?.response?.data;
    const errors = {};
    let message = 'An unexpected error occurred.';

    if (data) {
        if (typeof data.error === 'string') {
            message = data.error;
        } else if (typeof data.error?.message === 'string') {
            message = data.error.message;
        } else if (typeof data.message === 'string') {
            message = data.message;
        }

        // Extract field validation errors from data.error.details or data.errors
        const fieldErrors = data.error?.details || data.errors || {};
        if (typeof fieldErrors === 'object' && fieldErrors !== null) {
            for (const [field, val] of Object.entries(fieldErrors)) {
                if (Array.isArray(val) && val.length > 0) {
                    errors[field] = String(val[0]);
                } else if (typeof val === 'string') {
                    errors[field] = val;
                }
            }
        }
    } else if (err?.message) {
        message = err.message;
    }

    return {
        message,
        errors,
        hasFieldErrors: Object.keys(errors).length > 0,
    };
}
