import { Form, Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy as revokeTransfer,
    store as storeTransfer,
} from '@/routes/ownership-transfers';
import {
    destroy as revokeInvitation,
    resend as resendInvitation,
    store as storeInvitation,
} from '@/routes/team-invitations';
import {
    destroy as removeMembership,
    leave as leaveTeam,
    update as updateMembership,
} from '@/routes/team-memberships';
import { edit } from '@/routes/team-settings';

type Member = {
    id: number;
    user_id: number;
    name: string;
    email: string;
    role: 'owner' | 'administrator' | 'collaborator';
    can_change_role: boolean;
    can_remove: boolean;
};

type Invitation = {
    id: number;
    email: string;
    role: 'administrator' | 'collaborator';
    status: 'pending' | 'expired';
    expires_at: string;
    can_revoke: boolean;
    can_resend: boolean;
};

type Activity = {
    id: number;
    actor_name: string;
    action: string;
    occurred_at: string;
};

type Props = {
    team: { id: number; name: string; status: 'active' | 'deactivated' };
    members: Member[];
    invitations: Invitation[];
    pendingTransfer: {
        id: number;
        recipient_name: string;
        recipient_email: string;
        expires_at: string;
    } | null;
    activities?: Activity[];
    activityPagination?: { previous: string | null; next: string | null };
    can: {
        inviteAdministrator: boolean;
        inviteCollaborator: boolean;
        transferOwnership: boolean;
        viewActivity: boolean;
        leave: boolean;
    };
};

const roleLabel = (role: string) =>
    role
        .replace('_', ' ')
        .replace(/^./, (character) => character.toUpperCase());

const actionLabel = (action: string) =>
    action
        .replaceAll('_', ' ')
        .replace(/^./, (character) => character.toUpperCase());

export default function TeamSettings({
    team,
    members,
    invitations,
    pendingTransfer,
    activities,
    activityPagination,
    can,
}: Props) {
    const canInvite = can.inviteAdministrator || can.inviteCollaborator;
    const transferCandidates = members.filter(
        (member) => member.role !== 'owner',
    );

    return (
        <>
            <Head title={`${team.name} settings`} />
            <h1 className="sr-only">{team.name} settings</h1>

            <Card>
                <CardHeader>
                    <CardTitle>Team Members</CardTitle>
                    <CardDescription>
                        Active access to {team.name}. Membership history remains
                        after departure.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {members.map((member) => (
                        <div
                            key={member.id}
                            className="grid gap-3 rounded-lg border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="truncate font-medium">
                                        {member.name}
                                    </p>
                                    <Badge variant="secondary">
                                        {roleLabel(member.role)}
                                    </Badge>
                                </div>
                                <p className="truncate text-sm text-muted-foreground">
                                    {member.email}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {member.can_change_role ? (
                                    <Form
                                        {...updateMembership.form(member.id)}
                                        className="flex gap-2"
                                    >
                                        {({ processing }) => (
                                            <>
                                                <select
                                                    name="role"
                                                    defaultValue={member.role}
                                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                                    aria-label={`Role for ${member.name}`}
                                                >
                                                    <option value="administrator">
                                                        Administrator
                                                    </option>
                                                    <option value="collaborator">
                                                        Collaborator
                                                    </option>
                                                </select>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    {processing ? (
                                                        <Spinner />
                                                    ) : null}{' '}
                                                    Save
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                ) : null}
                                {member.can_remove ? (
                                    <Form {...removeMembership.form(member.id)}>
                                        {({ processing }) => (
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                disabled={processing}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </Form>
                                ) : null}
                            </div>
                        </div>
                    ))}
                </CardContent>
            </Card>

            {canInvite ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Invite a Team Member</CardTitle>
                        <CardDescription>
                            Membership begins only after the recipient accepts.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...storeInvitation.form()}
                            resetOnSuccess
                            className="grid gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-end"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invitation-email">
                                            Email address
                                        </Label>
                                        <Input
                                            id="invitation-email"
                                            name="email"
                                            type="email"
                                            required
                                        />
                                        {errors.email ? (
                                            <p className="text-sm text-destructive">
                                                {errors.email}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invitation-role">
                                            Offered role
                                        </Label>
                                        <select
                                            id="invitation-role"
                                            name="role"
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            {can.inviteAdministrator ? (
                                                <option value="administrator">
                                                    Administrator
                                                </option>
                                            ) : null}
                                            {can.inviteCollaborator ? (
                                                <option value="collaborator">
                                                    Collaborator
                                                </option>
                                            ) : null}
                                        </select>
                                    </div>
                                    <Button disabled={processing}>
                                        {processing ? <Spinner /> : null} Send
                                        Invitation
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            ) : null}

            {can.viewActivity && invitations.length > 0 ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Team Invitations</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {invitations.map((invitation) => (
                            <div
                                key={invitation.id}
                                className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p className="font-medium">
                                        {invitation.email}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {roleLabel(invitation.role)} /{' '}
                                        {roleLabel(invitation.status)} / expires{' '}
                                        {new Date(
                                            invitation.expires_at,
                                        ).toLocaleDateString()}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    {invitation.can_resend ? (
                                        <Form
                                            {...resendInvitation.form(
                                                invitation.id,
                                            )}
                                        >
                                            <Button size="sm" variant="outline">
                                                Resend
                                            </Button>
                                        </Form>
                                    ) : null}
                                    {invitation.can_revoke ? (
                                        <Form
                                            {...revokeInvitation.form(
                                                invitation.id,
                                            )}
                                        >
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                            >
                                                Revoke
                                            </Button>
                                        </Form>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            ) : null}

            {can.transferOwnership ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Ownership Transfer</CardTitle>
                        <CardDescription>
                            The current Owner remains responsible until the
                            recipient accepts.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {pendingTransfer ? (
                            <div className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-sm">
                                    Pending for{' '}
                                    <strong>
                                        {pendingTransfer.recipient_name}
                                    </strong>{' '}
                                    ({pendingTransfer.recipient_email})
                                </p>
                                <Form
                                    {...revokeTransfer.form(pendingTransfer.id)}
                                >
                                    <Button variant="destructive" size="sm">
                                        Revoke Transfer
                                    </Button>
                                </Form>
                            </div>
                        ) : (
                            <Form
                                {...storeTransfer.form()}
                                className="flex flex-col gap-3 sm:flex-row sm:items-end"
                            >
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="transfer-membership">
                                        New Owner
                                    </Label>
                                    <select
                                        id="transfer-membership"
                                        name="membership_id"
                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                        required
                                    >
                                        <option value="">
                                            Select a Team Member
                                        </option>
                                        {transferCandidates.map((member) => (
                                            <option
                                                key={member.id}
                                                value={member.id}
                                            >
                                                {member.name} ({member.email})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <Button type="submit">Propose Transfer</Button>
                            </Form>
                        )}
                    </CardContent>
                </Card>
            ) : null}

            {can.leave ? (
                <Card className="border-destructive/30">
                    <CardHeader>
                        <CardTitle>Leave Team</CardTitle>
                        <CardDescription>
                            Your access ends immediately. A new invitation is
                            required to return.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form {...leaveTeam.form()}>
                            <Button variant="destructive">
                                Leave {team.name}
                            </Button>
                        </Form>
                    </CardContent>
                </Card>
            ) : null}

            {can.viewActivity ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Team Activity</CardTitle>
                        <CardDescription>
                            Recent membership, invitation, role, and ownership
                            changes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-1">
                        {activities?.length ? (
                            activities.map((activity) => (
                                <div
                                    key={activity.id}
                                    className="flex flex-col gap-1 border-b py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <p className="text-sm">
                                        <strong>{activity.actor_name}</strong> /{' '}
                                        {actionLabel(activity.action)}
                                    </p>
                                    <time className="text-xs text-muted-foreground">
                                        {new Date(
                                            activity.occurred_at,
                                        ).toLocaleString()}
                                    </time>
                                </div>
                            ))
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No Team Activity yet.
                            </p>
                        )}
                        {activityPagination?.previous ||
                        activityPagination?.next ? (
                            <div className="flex justify-end gap-2 pt-3">
                                {activityPagination.previous ? (
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={activityPagination.previous}
                                            preserveScroll
                                        >
                                            Newer
                                        </Link>
                                    </Button>
                                ) : null}
                                {activityPagination.next ? (
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={activityPagination.next}
                                            preserveScroll
                                        >
                                            Older
                                        </Link>
                                    </Button>
                                ) : null}
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            ) : null}
        </>
    );
}

TeamSettings.layout = {
    breadcrumbs: [{ title: 'Team settings', href: edit() }],
};
