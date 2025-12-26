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
3. **Changelog** - Update the changelog in readme.txt

The changelog in readme.txt is public (for plugin distribution), so only include user-facing feature changes - no internal/deployment details.

### Security
Distributed plugin files (php, css, js, txt) must NOT contain:
- SSH credentials, IPs, ports, or usernames
- Staging/dev URLs
- Any deployment or infrastructure details

These details belong only in `.claude/` which is not distributed.
