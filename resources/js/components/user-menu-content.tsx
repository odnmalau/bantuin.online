import { Link, router, usePage } from '@inertiajs/react';
import { BookOpen, Github, LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { SharedData, User } from '@/types';

const repositoryUrl = 'https://github.com/odnmalau/bantuin.online';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { docsUrl } = usePage<SharedData>().props;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings />
                        Settings
                    </Link>
                </DropdownMenuItem>
                {docsUrl ? (
                    <DropdownMenuItem asChild>
                        <a
                            className="block w-full cursor-pointer"
                            href={docsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={cleanup}
                        >
                            <BookOpen />
                            Documentation
                        </a>
                    </DropdownMenuItem>
                ) : null}
                <DropdownMenuItem asChild>
                    <a
                        className="block w-full cursor-pointer"
                        href={repositoryUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick={cleanup}
                    >
                        <Github />
                        Repository
                    </a>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout.url()}
                    method="post"
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
