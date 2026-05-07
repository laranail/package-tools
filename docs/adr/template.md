# ADR-NNNN — Title (active voice, ≤80 chars)

- **Status:** Proposed | Accepted | Superseded by ADR-NNNN | Deprecated
- **Date:** YYYY-MM-DD
- **Deciders:** Names / roles
- **Scope:** Suite-wide | `<package-name>` | A specific subsystem

## Context

The forces at play. Why this decision is up for grabs *now* — what
changed, what broke, what we learned. Two or three short paragraphs.

If quantitative: include the numbers (e.g. "47 broken namespace
references", "210/312 tests passing"). If qualitative: name the trade-
off honestly. Don't editorialise — let the next reader decide.

## Decision

The choice. State it as plainly as possible. Use bullet points when
there are multiple sub-decisions or rules.

If naming things: list the names + their purpose. If choosing between
options: state the chosen option and (briefly) why the others were not.

## Consequences

What follows from this decision. Both the wins and the costs.

- **Wins**: what becomes easier, cheaper, or possible.
- **Costs**: what becomes harder, more expensive, or required.
- **Migration**: what existing code/users have to change, if anything.
- **Reversibility**: how hard is it to walk this back if it's wrong?

## Alternatives considered

(Optional but encouraged.) Briefly: what was the second-best option,
and why was it rejected? Future readers will ask this question; answer
it once.

---

## How to use this template

1. Copy this file to `NNNN-short-kebab-title.md` where `NNNN` is the
   next free number (suite-wide ADRs in `package-tools` start at 0001;
   per-package ADRs in other repos start at 0100).
2. Fill in every section. Empty sections suggest the ADR was not
   thought through.
3. Open a PR titled `docs(adr): NNNN — <title>`. Status starts as
   "Proposed". Mark "Accepted" when merged.
4. Once accepted, **never edit the ADR**. If the decision changes,
   write a new ADR that supersedes the old (and add `Superseded by:
   ADR-NNNN` to the original's status line).
5. Update `docs/adr/README.md` to add the new ADR to the index.

References: [adr.github.io](https://adr.github.io/),
[Michael Nygard's original article](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions).
