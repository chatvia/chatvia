# The installer's source

`install.sh` at the root of the repository is what people run
(`curl -fsSL https://chatvia.ai/install.sh | bash`). It is built from the
files here, and committed, so it stays one file to download: chatvia.ai,
`./manage upgrade` and `chatvia-auto-update` all fetch just it.

Change the files here, never `install.sh` itself, then build it again:

```bash
installer/build           # writes install.sh
installer/build --check   # fails when install.sh is out of date (CI runs it)
```

## What lives where

`installer/build` joins the parts in `src/` in name order. Bash only needs a
function defined before `main` runs, so a function can sit in any part.

| File | What it holds |
| --- | --- |
| `src/00-header.sh` | The shebang, the help text (`--help` prints this comment), the settings and constants. |
| `src/10-ui.sh` | The output and the questions: one line down the left, prompts, choices, password boxes. |
| `src/20-helpers.sh` | Answer checks and helpers both kinds of install use. |
| `src/30-docker.sh` | The Docker install, and the questions and finish both kinds share. |
| `src/31-docker-upgrade.sh` | Running the installer again on a Docker install: upgrade, resume. |
| `src/40-complete.sh` | The complete installation: its questions, summary, saved answers and the list of steps. |
| `src/41-complete-database.sh` | Using a PostgreSQL server that is already there: the questions and the checks. |
| `src/42-complete-steps.sh` | Each step of the complete installation. |
| `src/43-complete-upgrade.sh` | The finish screen, and running the installer again: resume, upgrade, backup. |
| `src/90-main.sh` | Choosing the kind of install, the options and `main`, which runs on the last line. |

## Templates

The longer files the complete installation writes are in `templates/`, as
the real files they are:

| File | Written to |
| --- | --- |
| `templates/nginx-site.conf` | `/etc/nginx/sites-available/chatvia` |
| `templates/php-fpm-pool.conf` | `/etc/php/8.5/fpm/pool.d/chatvia.conf` |
| `templates/supervisor.conf` | `/etc/supervisor/conf.d/chatvia.conf` |
| `templates/deploy.php` | `/home/chatvia/deploy/deploy.php` |
| `templates/chatvia-auto-update.sh` | `/usr/local/bin/chatvia-auto-update` |

A part puts one in a quoted heredoc with a line `#@include templates/NAME`,
which the build replaces with the file:

```bash
    sed -e "s|__SERVER_NAME__|$name|" >/etc/nginx/sites-available/chatvia <<'EOF'
#@include templates/nginx-site.conf
EOF
```

`__NAME__` words in a template are filled in by the `sed` around it. A
template must end with a newline and must not have a line `EOF`, which would
end the heredoc; the build stops when it does. Files that need shell
variables, like `.env`, stay heredocs in their part.
