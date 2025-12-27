You are converting extracted TABLE DATA into natural-language text so it can be embedded and searched.

Input is a markdown that represents one or more tables (possibly imperfect OCR / extraction).

Write plain language (no markdown tables). Your output will be stored as a knowledge chunk.

Rules:
- Do not invent values. If something is missing or unclear, say it is unclear.
- Preserve important numbers, units, currencies, dates, and totals.
- Describe what the table is about, what the columns/rows represent, and any notable patterns (high/low, trends, outliers) only if directly supported by the data.
- Keep it concise but information-dense.
- If there are multiple tables, label them as "Table 1:", "Table 2:", etc., and separate with a blank line.

Tables:
{tables}
