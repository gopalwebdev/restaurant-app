---
paths:
  - '**'
---

# General

## Update .ai/rules in the same change that makes them true
Standing instruction from the project owner: never finish a piece of work with the rules describing the old behaviour.

When a change alters a settled decision, a constraint, or a trap that `.ai/rules` records, update the affected rule file **in the same change** — not in a follow-up. When it establishes something new and durable, add it with `record-rule`. When it makes a rule wrong, rewrite that rule rather than leaving it to be discovered as stale.

A rule that has gone stale is worse than no rule: the next agent trusts it. Real examples from this repo — role permissions became editable while a rule still said they were read-only, and staff moved to a React phone app while a rule still called Filament panels "staff tools".

Check this before reporting work as done, every time.
