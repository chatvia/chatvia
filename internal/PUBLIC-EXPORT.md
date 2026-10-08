# Building the public release

The public (open-source) repository is **not** this repository with its history.
It is a clean copy of one commit, published as a fresh history. This keeps the
private history (old credentials, internal documents, production details) out
of the public repository.

`scripts/export-public.sh` builds that clean copy.

## How it works

1. `git archive` copies the files that are **committed** at the chosen ref
   (default `HEAD`). Untracked files, ignored files and uncommitted changes are
   never exported.
2. Every path marked `export-ignore` in `.gitattributes` is skipped:
   `server/`, `server.sh`, `deploy.php`, `.github/workflows/deploy.yml`,
   `postgresql.conf`, `Caddyfile`, `widget/.github/`, `internal/`, the export
   script itself, and the AI-assistant settings (`CLAUDE.md`, `AGENTS.md`,
   `.cursor/`, `.junie/`, `.claude/`).
3. The script checks the result and **fails** (exit code 1) when it finds:
   - a private path that was exported anyway;
   - a secret-bearing file (`.env`, `auth.json`, `*.pem`, `*.key`, SSH keys);
   - a known private marker: the production server IP, the maintainer's
     personal e-mail addresses, the cloud project id, local `/Users/...` paths;
   - a private key, a live Stripe key, or a `PASSWORD '...'` with a real value.

   It prints only the file and line of each finding, never the matched text.

The list of private markers lives in the script. The script is export-ignored,
so the list itself is never published.

## Steps

```bash
# 1. Commit everything you want to publish. The export ignores uncommitted work.
git status

# 2. Export into an empty folder.
scripts/export-public.sh ../chatvia-public          # exports HEAD
scripts/export-public.sh ../chatvia-public v1.2.0   # or a tag / branch

# 3. If it fails, fix the findings here (or add an export-ignore rule),
#    commit, delete the folder and export again.

# 4. Review the tree, then publish it as a fresh history.
cd ../chatvia-public
git init -b main
git add -A
git commit -m "Chatvia <version>"
git remote add origin git@github.com:<org>/<public-repo>.git
git push -u origin main
```

For later releases, export again into an empty folder and sync it into a
checkout of the public repository, so deleted files are removed too:

```bash
rsync -a --delete --exclude .git ../chatvia-export/ ../chatvia-public/
cd ../chatvia-public && git status   # review, then commit
```

## Adding a new private path

1. Add it to `.gitattributes` with `export-ignore` (no trailing slash for folders).
2. Add it to the `private_paths` list in `scripts/export-public.sh`.
3. Run the script and check that the path is gone.
