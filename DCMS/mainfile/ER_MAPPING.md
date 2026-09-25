# EER → Website mapping

The supplied EER is treated as the authoritative business model. The website deliberately does **not** expose every EER entity as a user-facing page.

## User-facing views

`DEBATE_ROUND + DEBATE_ASSIGNMENT + ROOM + PARTICIPATES_IN + TEAM` are presented together as the **Schedule** and **Debate Details** experience. This lets a normal user search/filter a debate without needing to understand the relational implementation.

`SCORE_ENTRY + TEAM_TOTALS` are presented as **Results & Rankings** and as the score section on each debate detail page.

`PARTICIPANT + INSTITUTION` are presented as the user's **Profile** after signup.

`JUDGE` and `ASSIGNMENT_JUDGE` are shown on debate details, rather than as independent user-facing CRUD screens.

## Admin-facing data management

The admin console exposes editing where it is operationally useful:

- Rounds → `DEBATE_ROUND`
- Assignments → `DEBATE_ASSIGNMENT`, `USES`, `PART_OF_ROUND`
- Teams → `TEAM`
- Participants → `PARTICIPANT`
- Debater role → `DEBATER`, `BELONGS_TO`
- Judge role → `JUDGE`, `JUDGED_BY`
- Side allocation → `PARTICIPATES_IN`
- Speaker score → `SCORE_ENTRY`, `FOR_DEBATER`, `FOR_ASSIGNMENT`
- Team totals/rank → `TEAM_TOTALS`, `FOR_DEBATE`, `FOR_ASSIGNMENT`
- Rooms → `ROOM`
- Institutions → `INSTITUTION`, `SPONSORS`

## Important EER constraint preserved

The specialization from PARTICIPANT into DEBATER/JUDGE is drawn with `d` (disjoint) in the supplied EER. The admin UI therefore prevents creating the same participant as both roles in the same operation.

## Authentication addition

`auth_user` is a website implementation table, not an attempt to alter the supplied event EER. It stores hashed passwords and the user/admin role, and links ordinary accounts to `PARTICIPANT`.

## Scoring rule

The supplied model exposes `SpeakerScore`, `TotalPoints` and `TeamRank`, but it does not give a calculation formula connecting them. The application therefore treats `TotalPoints` as an official admin-entered value and ranks teams within an assignment by descending total. It does not invent an unprovided formula.
