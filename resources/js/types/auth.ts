export type User = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'candidate';
    avatar?: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type AuthFeatures = {
    google: boolean;
};

export type SharedData = {
    name: string;
    auth: Auth;
    authFeatures: AuthFeatures;
    sidebarOpen: boolean;
};
