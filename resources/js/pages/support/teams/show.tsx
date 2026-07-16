import { Form, Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { deactivate, index, reactivate } from '@/routes/support/teams';
import membershipRepairs from '@/routes/support/teams/membership-repairs';
import ownershipTransfers from '@/routes/support/teams/ownership-transfers';

type Membership = {
    id: number;
    name: string;
    email: string;
    role: string;
    ended_at: string | null;
};
type Invitation = {
    id: number;
    email: string;
    role: string;
    status: string;
    actor_context: string;
    expires_at: string;
};
type Props = {
    team: { id: number; name: string; status: 'active' | 'deactivated' };
    memberships: Membership[];
    invitations: Invitation[];
    counts: Record<string, number>;
};

export default function SupportTeam({
    team,
    memberships,
    invitations,
    counts,
}: Props) {
    const activeRecipients = memberships.filter(
        (membership) => !membership.ended_at && membership.role !== 'owner',
    );

    return (
        <>
            <Head title={`Support ${team.name}`} />
            <div className="grid gap-6">
                <div className="flex flex-col gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-widest text-amber-700 uppercase dark:text-amber-300">
                            Platform Support mode
                        </p>
                        <h1 className="text-2xl font-semibold">{team.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            Support metadata only. This Team is not your Current
                            Team.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={index()}>All Teams</Link>
                    </Button>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {Object.entries(counts).map(([label, count]) => (
                        <Card key={label}>
                            <CardContent className="pt-6">
                                <strong className="text-2xl">{count}</strong>
                                <p className="text-sm text-muted-foreground">
                                    {label.replaceAll('_', ' ')}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Team Membership metadata</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {memberships.map((membership) => (
                            <div
                                key={membership.id}
                                className="flex flex-wrap justify-between gap-2 border-b py-2"
                            >
                                <span>
                                    {membership.name} ({membership.email})
                                </span>
                                <Badge variant="secondary">
                                    {membership.role}
                                    {membership.ended_at ? ' / ended' : ''}
                                </Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Repair membership access</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...membershipRepairs.store.form(team.id)}
                            resetOnSuccess
                            className="grid gap-3 sm:grid-cols-4"
                        >
                            <Input
                                name="email"
                                type="email"
                                required
                                placeholder="Recipient email"
                            />
                            <Select name="role" defaultValue="collaborator">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="collaborator">
                                            Collaborator
                                        </SelectItem>
                                        <SelectItem value="administrator">
                                            Administrator
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Input
                                name="reason"
                                required
                                placeholder="Support reason"
                            />
                            <Button>Send repair invitation</Button>
                        </Form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Ownership Transfer</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...ownershipTransfers.store.form(team.id)}
                            className="grid gap-3 sm:grid-cols-3"
                        >
                            <Select name="membership_id" required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Eligible active Team Member" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {activeRecipients.map((membership) => (
                                            <SelectItem
                                                key={membership.id}
                                                value={String(membership.id)}
                                            >
                                                {membership.name} (
                                                {membership.role})
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Input
                                name="reason"
                                required
                                placeholder="Support reason"
                            />
                            <Button>Initiate transfer</Button>
                        </Form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Lifecycle</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {team.status === 'active' ? (
                            <Form
                                {...deactivate.form(team.id)}
                                className="flex flex-col gap-3 sm:flex-row"
                            >
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="deactivate-reason">
                                        Support reason
                                    </Label>
                                    <Input
                                        id="deactivate-reason"
                                        name="reason"
                                        required
                                    />
                                </div>
                                <Button
                                    className="self-end"
                                    variant="destructive"
                                >
                                    Deactivate
                                </Button>
                            </Form>
                        ) : (
                            <Form
                                {...reactivate.form(team.id)}
                                className="flex flex-col gap-3 sm:flex-row"
                            >
                                <Input
                                    name="reason"
                                    required
                                    placeholder="Support reason"
                                />
                                <Button>Reactivate</Button>
                            </Form>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Team Invitation metadata</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {invitations.map((invitation) => (
                            <div
                                key={invitation.id}
                                className="flex flex-wrap justify-between gap-2 border-b py-2"
                            >
                                <span>{invitation.email}</span>
                                <span className="text-sm text-muted-foreground">
                                    {invitation.role} / {invitation.status} /{' '}
                                    {invitation.actor_context}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
