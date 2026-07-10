import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { update } from '@/routes/current-team';
import type { SharedData } from '@/types';

type Props = {
    className?: string;
};

export function TeamSwitcher({ className }: Props) {
    const { auth } = usePage<SharedData>().props;

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    className={cn('min-w-0 justify-between', className)}
                >
                    <Building2 />
                    <span className="truncate">
                        {auth.currentTeam?.name ?? 'Personal'}
                    </span>
                    <ChevronsUpDown className="text-muted-foreground" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel>Current Team</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {auth.teams.map((team) => (
                    <DropdownMenuItem
                        key={team.id}
                        onSelect={() =>
                            router.put(update.url(), { team_id: team.id })
                        }
                    >
                        <span className="min-w-0 flex-1 truncate">
                            {team.name}
                        </span>
                        <span className="text-xs text-muted-foreground capitalize">
                            {team.role}
                        </span>
                        {team.id === auth.currentTeam?.id ? <Check /> : null}
                    </DropdownMenuItem>
                ))}
                {auth.teams.length === 0 ? (
                    <DropdownMenuItem disabled>
                        Create a Team from Dashboard
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
