import { create } from 'zustand';

export const useAuthStore = create((set, get) => ({
    user: (() => {
        try {
            const raw = localStorage.getItem('auth_user');
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    })(),
    token: localStorage.getItem('auth_token') || null,
    isAuthenticated: !!localStorage.getItem('auth_token'),

    setAuth: (user, token) => {
        if (token) {
            localStorage.setItem('auth_token', token);
        }
        if (user) {
            localStorage.setItem('auth_user', JSON.stringify(user));
        }
        set({
            user,
            token: token || get().token,
            isAuthenticated: true,
        });
    },

    setUser: (user) => {
        if (user) {
            localStorage.setItem('auth_user', JSON.stringify(user));
        } else {
            localStorage.removeItem('auth_user');
        }
        set({ user });
    },

    logout: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        set({
            user: null,
            token: null,
            isAuthenticated: false,
        });
    },

    hasRole: (roles) => {
        const user = get().user;
        if (!user || !user.role) return false;
        if (Array.isArray(roles)) {
            return roles.includes(user.role);
        }
        return user.role === roles;
    },

    isAdmin: () => {
        return get().user?.role === 'admin';
    },

    isAgent: () => {
        return ['admin', 'agent'].includes(get().user?.role);
    },
}));
