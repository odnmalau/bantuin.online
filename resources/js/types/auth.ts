export type User = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'candidate';
    avatar?: string;
};

export type CurrentTeam = {
    id: number;
    name: string;
    status: 'active' | 'deactivated';
    role: 'owner' | 'administrator' | 'collaborator';
};

export type Auth = {
    user: User | null;
    currentTeam: CurrentTeam | null;
    platformOperator: boolean;
};

export type AuthFeatures = {
    google: boolean;
};

export type SharedData = {
    name: string;
    docsUrl: string | null;
    auth: Auth;
    authFeatures: AuthFeatures;
    sidebarOpen: boolean;
};
