import { create } from 'zustand';

const MAX_STORED_MESSAGES = 100;

export const useBroadcastStore = create((set, get) => ({
    messages: [],
    isOpen: false,

    setIsOpen: (open) => set({ isOpen: open }),
    toggleOpen: () => set((state) => ({ isOpen: !state.isOpen })),

    addBroadcastMessage: ({ type, channel, title, description, ticketId = null, payload = null }) => {
        const id = `${Date.now()}-${Math.random().toString(36).substring(2, 7)}`;
        const newMessage = {
            id,
            type,
            channel,
            title,
            description,
            ticketId,
            payload,
            timestamp: new Date().toISOString(),
            read: false,
        };

        set((state) => ({
            messages: [newMessage, ...state.messages].slice(0, MAX_STORED_MESSAGES),
        }));

        return id;
    },

    markAsRead: (id) => {
        set((state) => ({
            messages: state.messages.map((msg) =>
                msg.id === id ? { ...msg, read: true } : msg
            ),
        }));
    },

    markAllAsRead: () => {
        set((state) => ({
            messages: state.messages.map((msg) => ({ ...msg, read: true })),
        }));
    },

    clearAll: () => {
        set({ messages: [] });
    },
}));
