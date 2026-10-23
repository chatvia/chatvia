When someone asks an agent a question, the agent searches its sources for the parts that answer it.

- **Hybrid search** — Searches by meaning and by keywords together, then merges the results. Keywords find exact names, product codes and rare words better. Off searches by meaning only. You can turn it on or off at any time.
  The keyword part uses pg_textsearch (BM25 ranking) when it is installed, and PostgreSQL's built-in full-text search when it is not. The **Status** page shows which one is in use.
- **Results per question** — The most parts of the sources an agent gets for one question, from 1 to 20. When weak matches are left out (under **Advanced**), an agent may get fewer.

**Tip:** Not sure? Keep the defaults. An empty field uses the default shown in it.
