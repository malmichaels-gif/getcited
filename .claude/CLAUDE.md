# GetCited - Claude Code Instructions

## Project Overview
GetCited is a WordPress plugin that helps sites get cited by AI assistants (ChatGPT, Perplexity, Claude, etc.) by generating `llms.txt` files and optimizing for AI discoverability.

## Skills
Always use the `frontend-design` skill when creating or modifying UI components, styles, or templates.

## Deployment

### Dev/Staging Site
- **URL:** https://stg-heytccom-devheytc.kinsta.cloud/
- **Dashboard:** https://stg-heytccom-devheytc.kinsta.cloud/wp-admin/admin.php?page=getcited

### Auto-Deploy on Changes
Automatically deploy to dev after making plugin changes:

```bash
# Deploy all plugin files
scp -P 24772 -r getcited.php readme.txt LICENSE.txt uninstall.php heytccom@35.196.5.93:/www/heytccom_324/public/wp-content/plugins/getcited/
scp -P 24772 -r includes/* heytccom@35.196.5.93:/www/heytccom_324/public/wp-content/plugins/getcited/includes/
scp -P 24772 -r templates/* heytccom@35.196.5.93:/www/heytccom_324/public/wp-content/plugins/getcited/templates/
scp -P 24772 -r assets/* heytccom@35.196.5.93:/www/heytccom_324/public/wp-content/plugins/getcited/assets/
```

### SSH Details
| Setting | Value |
|---------|-------|
| Host | 35.196.5.93 |
| Port | 24772 |
| User | heytccom |
| Plugin Path | /www/heytccom_324/public/wp-content/plugins/getcited/ |

## Workflow

### Version Bumps
When releasing a new version, **always update ALL THREE locations**:
1. `getcited.php` line 6: `* Version: X.X.X`
2. `getcited.php` line 23: `define( 'GETCITED_VERSION', 'X.X.X' );`
3. `readme.txt` line 7: `Stable tag: X.X.X`

The WordPress Plugin Check will fail if `Stable tag` doesn't match the plugin header version.

### After Making Changes
After completing changes, ask the user if they want to:
1. **Commit** - Stage and commit the changes
2. **Tag** - Create a git tag for the version
3. **Push** - Push to GitHub
4. **Changelog** - Update the changelog in readme.txt

The changelog in readme.txt is public (for plugin distribution), so only include user-facing feature changes - no internal/deployment details.

### Git & GitHub Workflow
**Repository:** https://github.com/malmichaels-gif/getcited

When user wants to commit & tag a release:

```bash
# 1. Stage all changes
git add -A

# 2. Commit with version message
git commit -m "vX.X.X: Description of changes"

# 3. Create annotated tag
git tag -a vX.X.X -m "Version X.X.X"

# 4. Push commit and tags to GitHub
git push origin master
git push origin --tags
```

Or as a single command after staging:
```bash
git add -A && git commit -m "vX.X.X: Description" && git tag -a vX.X.X -m "Version X.X.X" && git push origin master && git push origin --tags
```

### Security
Distributed plugin files (php, css, js, txt) must NOT contain:
- SSH credentials, IPs, ports, or usernames
- Staging/dev URLs
- Any deployment or infrastructure details

These details belong only in `.claude/` which is not distributed.

### Planning
- For non-trivial tasks (3+ steps or architectural decisions), write a plan first
- If something goes sideways, STOP and re-plan — don't keep pushing
- Write detailed specs upfront to reduce ambiguity

### Subagents
- Use subagents for research, exploration, and parallel analysis
- Keep main context focused — one task per subagent
- For complex problems, throw more compute at it via subagents

### Self-Improvement
- After ANY correction, update CLAUDE.md with a rule to prevent the same mistake
- Write rules for yourself that prevent recurring errors

### Verification
- Never mark a task complete without proving it works
- Diff behavior between main and your changes when relevant
- Show logs, output, or test results — don't just say "done"

### Elegance
- For non-trivial changes, pause and ask "is there a more elegant way?"
- If a fix feels hacky: implement the elegant solution instead
- Skip this for simple, obvious fixes — don't over-engineer

### Bug Fixing
- When given a bug report: just fix it. Don't ask for hand-holding.
- Point at logs, errors, failing tests — then resolve them

### Tech Currency (2026)
- Current year is 2026 — your training may be outdated
- Before implementing significant features, search for current best practices
- Flag when you're unsure if an approach is still recommended
- Don't assume libraries, APIs, or frameworks work the same as in your general knowledge

## Core Principles

- **Simplicity First:** Make every change as simple as possible. Minimal code.
- **No Laziness:** Find root causes. No temporary fixes. Senior developer standards.
- **Minimal Impact:** Changes should only touch what's necessary. Avoid introducing bugs.
