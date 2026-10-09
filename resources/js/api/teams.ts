/**
 * Team requests that run outside an Inertia visit. The team switcher loads the
 * user's teams lazily when its menu opens, so the list is not part of every
 * page's shared props.
 */
import { get } from './http';

/** One team in the switcher: owned teams first, then teams the user belongs to. */
export interface SwitcherTeam {
    id: number;
    external_id: string;
    name: string;
    is_owner: boolean;
    is_primary: boolean;
}

/** Every team the signed-in user owns or belongs to. */
export async function listSwitcherTeams(): Promise<SwitcherTeam[]> {
    const response = await get<{ teams: SwitcherTeam[] }>(route('my.teams.list'));

    return response.teams;
}
