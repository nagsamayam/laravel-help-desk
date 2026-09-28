import React, { useState } from 'react';
import { useAuthStore } from '@/stores/auth-store';
import { useUiStore } from '@/stores/ui-store';
import { apiClient } from '@/lib/api-client';
import { extractApiErrors } from '@/lib/utils';
import { Button } from '@/components/ui/Button';
import { Input, Select } from '@/components/ui/Input';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/Tabs';
import { Ticket, Shield, User, Lock, Mail, UserCheck } from 'lucide-react';

export function AuthPage() {
    const { setAuth } = useAuthStore();
    const { addToast } = useUiStore();

    const [loginEmail, setLoginEmail] = useState('');
    const [loginPassword, setLoginPassword] = useState('');
    const [loginLoading, setLoginLoading] = useState(false);
    const [loginError, setLoginError] = useState('');
    const [loginFieldErrors, setLoginFieldErrors] = useState({});

    const [regFirstName, setRegFirstName] = useState('');
    const [regLastName, setRegLastName] = useState('');
    const [regEmail, setRegEmail] = useState('');
    const [regPassword, setRegPassword] = useState('');
    const [regPasswordConfirm, setRegPasswordConfirm] = useState('');
    const [regRole, setRegRole] = useState('customer');
    const [regLoading, setRegLoading] = useState(false);
    const [regError, setRegError] = useState('');
    const [regFieldErrors, setRegFieldErrors] = useState({});

    const handleLogin = async (e) => {
        e?.preventDefault();
        setLoginLoading(true);
        setLoginError('');
        setLoginFieldErrors({});

        try {
            const res = await apiClient.post('/auth/login', {
                email: loginEmail,
                password: loginPassword,
            });

            const data = res.data?.data || res.data;
            let user = data.user;
            const token = data.token || data.access_token;

            if (!user && token) {
                try {
                    const meRes = await apiClient.get('/auth/me', {
                        headers: { Authorization: `Bearer ${token}` },
                    });
                    user = meRes.data?.data || meRes.data;
                } catch {
                    // Fallback
                }
            }

            setAuth(user, token);
            addToast({
                type: 'success',
                title: 'Welcome back!',
                message: `Logged in as ${user?.name || user?.email || 'User'} (${user?.role || 'Authenticated'})`,
            });
        } catch (err) {
            const { message, errors } = extractApiErrors(err);
            setLoginError(message || 'Invalid credentials');
            setLoginFieldErrors(errors || {});
            addToast({
                type: 'error',
                title: 'Login failed',
                message: message || 'Invalid credentials',
            });
        } finally {
            setLoginLoading(false);
        }
    };

    const handleRegister = async (e) => {
        e?.preventDefault();
        setRegError('');
        setRegFieldErrors({});

        if (regPassword !== regPasswordConfirm) {
            setRegError('Passwords do not match');
            setRegFieldErrors({ password_confirmation: 'Passwords do not match' });
            return;
        }

        setRegLoading(true);

        try {
            const res = await apiClient.post('/auth/register', {
                first_name: regFirstName,
                last_name: regLastName,
                email: regEmail,
                password: regPassword,
                password_confirmation: regPasswordConfirm,
                role: regRole,
            });

            const data = res.data?.data || res.data;
            let user = data.user;
            const token = data.token || data.access_token;

            if (!user && token) {
                try {
                    const meRes = await apiClient.get('/auth/me', {
                        headers: { Authorization: `Bearer ${token}` },
                    });
                    user = meRes.data?.data || meRes.data;
                } catch {
                    // Fallback
                }
            }

            const userName = user?.name || `${regFirstName} ${regLastName}`.trim();
            setAuth(user, token);
            addToast({
                type: 'success',
                title: 'Account created!',
                message: `Registered as ${userName}`,
            });
        } catch (err) {
            const { message, errors } = extractApiErrors(err);
            setRegError(message || 'Registration failed');
            setRegFieldErrors(errors || {});
            addToast({
                type: 'error',
                title: 'Registration failed',
                message: message || 'Registration failed',
            });
        } finally {
            setRegLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-radial from-indigo-50/50 via-slate-50 to-slate-100 dark:from-slate-900 dark:via-slate-950 dark:to-slate-950 p-4">
            <div className="w-full max-w-md">
                <div className="text-center mb-8">
                    <div className="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 mb-3">
                        <Ticket className="w-6 h-6" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Help Desk Portal
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Modern Enterprise Support & Lifecycle Engine
                    </p>
                </div>

                <Card className="shadow-xl border-slate-200/80 dark:border-slate-800">
                    <CardHeader className="pb-3">
                        <Tabs defaultValue="login" className="w-full">
                            <TabsList className="grid w-full grid-cols-2 mb-4">
                                <TabsTrigger value="login">Sign In</TabsTrigger>
                                <TabsTrigger value="register">Register</TabsTrigger>
                            </TabsList>

                            <TabsContent value="login">
                                <form onSubmit={handleLogin} className="space-y-4">
                                    {loginError && (
                                        <div className="p-3 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 rounded-lg">
                                            {loginError}
                                        </div>
                                    )}

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            Email Address
                                        </label>
                                        <Input
                                            type="email"
                                            placeholder="agent@example.com"
                                            value={loginEmail}
                                            onChange={(e) => {
                                                setLoginEmail(e.target.value);
                                                if (loginFieldErrors.email) setLoginFieldErrors((prev) => ({ ...prev, email: undefined }));
                                            }}
                                            error={loginFieldErrors.email}
                                            required
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            Password
                                        </label>
                                        <Input
                                            type="password"
                                            placeholder="••••••••"
                                            value={loginPassword}
                                            onChange={(e) => {
                                                setLoginPassword(e.target.value);
                                                if (loginFieldErrors.password) setLoginFieldErrors((prev) => ({ ...prev, password: undefined }));
                                            }}
                                            error={loginFieldErrors.password}
                                            required
                                        />
                                    </div>

                                    <Button type="submit" className="w-full" loading={loginLoading}>
                                        Sign In
                                    </Button>
                                </form>
                            </TabsContent>

                            <TabsContent value="register">
                                <form onSubmit={handleRegister} className="space-y-3">
                                    {regError && (
                                        <div className="p-3 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 rounded-lg">
                                            {regError}
                                        </div>
                                    )}

                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                                First Name
                                            </label>
                                            <Input
                                                type="text"
                                                placeholder="Sarah"
                                                value={regFirstName}
                                                onChange={(e) => {
                                                    setRegFirstName(e.target.value);
                                                    if (regFieldErrors.first_name) setRegFieldErrors((prev) => ({ ...prev, first_name: undefined }));
                                                }}
                                                error={regFieldErrors.first_name}
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                                Last Name
                                            </label>
                                            <Input
                                                type="text"
                                                placeholder="Connor"
                                                value={regLastName}
                                                onChange={(e) => {
                                                    setRegLastName(e.target.value);
                                                    if (regFieldErrors.last_name) setRegFieldErrors((prev) => ({ ...prev, last_name: undefined }));
                                                }}
                                                error={regFieldErrors.last_name}
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            Email Address
                                        </label>
                                        <Input
                                            type="email"
                                            placeholder="sarah@example.com"
                                            value={regEmail}
                                            onChange={(e) => {
                                                setRegEmail(e.target.value);
                                                if (regFieldErrors.email) setRegFieldErrors((prev) => ({ ...prev, email: undefined }));
                                            }}
                                            error={regFieldErrors.email}
                                            required
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                                Password
                                            </label>
                                            <Input
                                                type="password"
                                                placeholder="••••••••"
                                                value={regPassword}
                                                onChange={(e) => {
                                                    setRegPassword(e.target.value);
                                                    if (regFieldErrors.password) setRegFieldErrors((prev) => ({ ...prev, password: undefined }));
                                                }}
                                                error={regFieldErrors.password}
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                                Confirm
                                            </label>
                                            <Input
                                                type="password"
                                                placeholder="••••••••"
                                                value={regPasswordConfirm}
                                                onChange={(e) => {
                                                    setRegPasswordConfirm(e.target.value);
                                                    if (regFieldErrors.password_confirmation) setRegFieldErrors((prev) => ({ ...prev, password_confirmation: undefined }));
                                                }}
                                                error={regFieldErrors.password_confirmation}
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            Role
                                        </label>
                                        <Select
                                            value={regRole}
                                            onChange={(e) => {
                                                setRegRole(e.target.value);
                                                if (regFieldErrors.role) setRegFieldErrors((prev) => ({ ...prev, role: undefined }));
                                            }}
                                            error={regFieldErrors.role}
                                        >
                                            <option value="customer">Customer</option>
                                            <option value="agent">Support Agent</option>
                                            <option value="admin">Administrator</option>
                                        </Select>
                                    </div>

                                    <Button type="submit" className="w-full mt-2" loading={regLoading}>
                                        Create Account
                                    </Button>
                                </form>
                            </TabsContent>
                        </Tabs>
                    </CardHeader>
                </Card>
            </div>
        </div>
    );
}
