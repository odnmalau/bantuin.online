<?php

use App\Support\TeamCapability;
use App\TeamMembershipRole;

test('role management matrix matches Owner and Administrator rules', function (?TeamMembershipRole $actorRole, TeamMembershipRole $targetRole, bool $expected) {
    expect(TeamCapability::canManageRole($actorRole, $targetRole))->toBe($expected);
})->with([
    'owner may manage owner' => [TeamMembershipRole::Owner, TeamMembershipRole::Owner, true],
    'owner may manage administrator' => [TeamMembershipRole::Owner, TeamMembershipRole::Administrator, true],
    'owner may manage collaborator' => [TeamMembershipRole::Owner, TeamMembershipRole::Collaborator, true],
    'administrator may manage collaborator' => [TeamMembershipRole::Administrator, TeamMembershipRole::Collaborator, true],
    'administrator may not manage administrator' => [TeamMembershipRole::Administrator, TeamMembershipRole::Administrator, false],
    'administrator may not manage owner' => [TeamMembershipRole::Administrator, TeamMembershipRole::Owner, false],
    'collaborator may not manage collaborator' => [TeamMembershipRole::Collaborator, TeamMembershipRole::Collaborator, false],
    'collaborator may not manage administrator' => [TeamMembershipRole::Collaborator, TeamMembershipRole::Administrator, false],
    'collaborator may not manage owner' => [TeamMembershipRole::Collaborator, TeamMembershipRole::Owner, false],
    'missing actor role may not manage collaborator' => [null, TeamMembershipRole::Collaborator, false],
    'missing actor role may not manage administrator' => [null, TeamMembershipRole::Administrator, false],
]);
