export type User = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'candidate';
    avatar?: string;
};

export type Auth = {
    user: User | null;
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
