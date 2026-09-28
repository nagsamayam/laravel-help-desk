export const queryKeys = {
    auth: {
        me: ['auth', 'me'],
    },
    tickets: {
        all: ['tickets'],
        list: (filters) => ['tickets', 'list', filters || {}],
        detail: (id) => ['tickets', 'detail', id],
        messages: (id) => ['tickets', 'detail', id, 'messages'],
        history: (id) => ['tickets', 'detail', id, 'history'],
        auditLogs: (id) => ['tickets', 'detail', id, 'audit-logs'],
    },
    users: {
        all: ['users'],
        agents: ['users', 'agents'],
    },
    categories: {
        all: ['categories'],
    },
    auditLogs: {
        all: ['audit-logs'],
        list: (filters) => ['audit-logs', 'list', filters || {}],
        detail: (id) => ['audit-logs', 'detail', id],
    },
};
