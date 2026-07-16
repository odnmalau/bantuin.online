import { Form, router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { update } from '@/routes/current-team';
import { store as storeTeam } from '@/routes/teams';
import type { SharedData } from '@/types';

type Props = {
    className?: string;
};

export function TeamSwitcher({ className }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [createOpen, setCreateOpen] = useState(false);

    if (!auth.user) {
        return null;
    }

    return (
        <>
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
                    <DropdownMenuGroup>
                        {auth.teams.map((team) => (
                            <DropdownMenuItem
                                key={team.id}
                                onSelect={() =>
                                    router.put(update.url(), {
                                        team_id: team.id,
                                    })
                                }
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {team.name}
                                </span>
                                {team.status === 'deactivated' ? (
                                    <span className="text-xs text-muted-foreground">
                                        Read-only
                                    </span>
                                ) : null}
                                <span className="text-xs text-muted-foreground capitalize">
                                    {team.role}
                                </span>
                                {team.id === auth.currentTeam?.id ? (
                                    <Check />
                                ) : null}
                            </DropdownMenuItem>
                        ))}
                        {auth.teams.length === 0 ? (
                            <DropdownMenuItem disabled>
                                No Teams yet
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setCreateOpen(true)}>
                            <Plus />
                            Create Team
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Team</DialogTitle>
                        <DialogDescription>
                            Create a separate workspace for Campaigns and hiring
                            activity.
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...storeTeam.form()}
                        resetOnSuccess
                        onSuccess={() => setCreateOpen(false)}
                        className="flex flex-col gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FieldGroup>
                                    <Field data-invalid={Boolean(errors.name)}>
                                        <FieldLabel htmlFor="new-team-name">
                                            Team name
                                        </FieldLabel>
                                        <Input
                                            id="new-team-name"
                                            name="name"
                                            placeholder="Product Hiring"
                                            aria-invalid={Boolean(errors.name)}
                                            required
                                        />
                                        <FieldError>{errors.name}</FieldError>
                                    </Field>
                                </FieldGroup>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? <Spinner /> : null}
                                        Create Team
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}
