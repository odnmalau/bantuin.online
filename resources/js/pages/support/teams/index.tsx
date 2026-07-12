import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { show } from '@/routes/support/teams';

type Team = {
    id: number;
    name: string;
    status: 'active' | 'deactivated';
    memberships_count: number;
    invitations_count: number;
    campaigns_count: number;
};

export default function SupportTeams({ teams }: { teams: { data: Team[] } }) {
    return (
        <>
            <Head title="Platform Support" />
            <div className="grid gap-6">
                <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-5">
                    <p className="text-xs font-semibold tracking-widest text-amber-700 uppercase dark:text-amber-300">
                        Separate authority
                    </p>
                    <h1 className="text-2xl font-semibold">Platform Support</h1>
                    <p className="text-sm text-muted-foreground">
                        Resolve Team access, ownership, and lifecycle incidents.
                        This is not Current Team mode.
                    </p>
                </div>
                <form className="max-w-md">
                    <Input
                        name="search"
                        placeholder="Search Team name"
                        aria-label="Search Teams"
                    />
                </form>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {teams.data.map((team) => (
                        <Link key={team.id} href={show(team.id)}>
                            <Card className="h-full transition-colors hover:border-amber-500/50">
                                <CardHeader>
                                    <CardTitle className="flex items-center justify-between gap-3">
                                        {team.name}
                                        <Badge variant="secondary">
                                            {team.status}
                                        </Badge>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-3 gap-2 text-center text-sm">
                                    <span>
                                        {team.memberships_count}
                                        <small className="block text-muted-foreground">
                                            Terms
                                        </small>
                                    </span>
                                    <span>
                                        {team.invitations_count}
                                        <small className="block text-muted-foreground">
                                            Invitations
                                        </small>
                                    </span>
                                    <span>
                                        {team.campaigns_count}
                                        <small className="block text-muted-foreground">
                                            Campaigns
                                        </small>
                                    </span>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}
