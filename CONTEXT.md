# HirePilot

HirePilot is a hiring assessment context for creating campaigns, authoring questions, inviting candidates, running secure exams, and reviewing AI-assisted assessment outcomes.

## Language

**Team**:
A hiring organization that owns its campaigns, invitations, assessments, and administrative membership.
_Avoid_: Organization, workspace, permission group

**Team Membership**:
A durable record of a user's relationship and role within a team. Ending membership revokes access but preserves its history.
_Avoid_: Membership row, team-user link

**Team Member**:
An administrative user with an active team membership who may access hiring work according to that membership. A team member cannot have candidate history in the same team.
_Avoid_: Candidate, employee, admin user

**Owner**:
The sole team member ultimately responsible for a team and its continued administration. A team always has exactly one owner, and only the owner controls administrator status.
_Avoid_: Team creator, super admin

**Ownership Transfer**:
A proposed handoff from the current owner to another active team member. Ownership changes only when the recipient accepts, after which the previous owner becomes an administrator.
_Avoid_: Owner assignment, ownership invitation

**Administrator**:
A team member who may manage collaborators, team invitations, and hiring work but does not own the team or control administrator status.
_Avoid_: Admin, manager

**Collaborator**:
A team member who may manage all hiring work within the team but not the team's membership or ownership.
_Avoid_: Member, recruiter

**Platform Operator**:
An internal user who may inspect team and membership metadata and repair ownership, access, and lifecycle state across teams. This authority is independent of team membership and grants no access to candidate content or hiring decisions.
_Avoid_: Super admin, global admin, owner

**Current Team**:
The one team whose hiring work a team member is presently viewing and managing.
_Avoid_: Active team, selected organization

**Deactivated Team**:
A non-empty team retained as read-only history. Its hiring work and membership cannot change until the team is reactivated.
_Avoid_: Archived team, deleted team

**Empty Team**:
A team with no campaigns, pending team invitations, or membership history beyond its owner.
_Avoid_: Unused team, team without assessments

**Candidate**:
A person invited to participate in a specific campaign. A candidate may be a team member of other teams, but candidate or membership history prohibits the other relationship within the same team.
_Avoid_: Candidate user, applicant role

**Team Invitation**:
An invitation sent to an email address to become a member of a team with a specified role. It creates no membership until accepted by a signed-in user with that email address.
_Avoid_: Invite, campaign invitation, pending member

**Team Activity**:
A durable history of sensitive team administration, including invitations, membership and role changes, ownership transfers, renames, lifecycle changes, and justified platform operator interventions.
_Avoid_: Audit log, application log

**Campaign**:
A hiring assessment initiative owned by exactly one team for its entire lifetime.
_Avoid_: Job, assessment campaign

**Authored Question**:
Normalized, validated question content before it becomes either a bank question or a campaign question.
_Avoid_: Question draft, question input
