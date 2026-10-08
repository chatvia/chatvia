# internal/ — private, not part of the public release

Everything in this folder is for the Chatvia maintainers only. It stays in the
private repository and is **left out of the public export**: `.gitattributes`
marks `internal/` as `export-ignore`, so `scripts/export-public.sh` (which uses
`git archive`) never copies it.

Do not link to these files from code, from the docs site (`docs/`) or from any
public file. They are not shipped and the links would be broken in the public
repository.

## What is here

| Path | What it is |
|---|---|
| `code-review.md` | An old internal code review. |
| `article.md` | Draft article about how Chatvia was built. |
| `acme-financials.txt`, `report.md` | Fictional "Acme" business documents used for manual demos of the knowledge base. |
| `compliance/` | Internal compliance work: the readiness plan, the policy set, the RoPA and the DPA review exports. |
| `PUBLIC-EXPORT.md` | How to build the public release from this repo. |

The compliance documents that the app **renders at runtime** are not here. They
stay in `docs/compliance/` because the legal pages read them:

- `docs/compliance/trust-center.md` (`/security`)
- `docs/compliance/gdpr/subprocessors.md` (`/subprocessors`)
- `docs/compliance/gdpr/data-processing-agreement.md` (`/dpa`)

## Adding a new private file

Put it in this folder. If a private file must live somewhere else (for example
production server config), add its path to `.gitattributes` with
`export-ignore`, and add it to the list of paths the export script checks.
