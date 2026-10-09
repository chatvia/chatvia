<?php

namespace Domain\Form\Actions;

use Domain\Form\Models\FormDefinition;

/**
 * Build a CSV of a form's submissions — one column per defined field plus a
 * submitted-at timestamp. Values are visitor-controlled, so each cell is
 * formula-injection-safe (a leading =,+,-,@,tab,CR is prefixed with a quote).
 */
class ExportSubmissionsCsvAction
{
    /**
     * @param  array<int, string>  $onlyExternalIds  Limit to these submissions (empty = all).
     */
    public function execute(FormDefinition $form, array $onlyExternalIds = []): string
    {
        $fields = array_values(array_filter(array_map(
            static fn (array $f): ?string => $f['key'] ?? null,
            $form->schema,
        )));
        $labels = [];
        foreach ($form->schema as $f) {
            if (isset($f['key'])) {
                $labels[$f['key']] = (string) ($f['label'] ?? $f['key']);
            }
        }

        $query = $form->submissions()
            ->submitted()
            ->latest('id');
        if ($onlyExternalIds !== []) {
            $query->whereIn('external_id', $onlyExternalIds);
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [...array_map(fn (string $k): string => $labels[$k] ?? $k, $fields), 'Submitted at']);

        foreach ($query->cursor() as $submission) {
            $row = [];
            foreach ($fields as $key) {
                $row[] = $this->csvSafe($submission->field($key) ?? '');
            }
            $row[] = optional($submission->submitted_at)->toDateTimeString() ?? '';
            fputcsv($handle, $row);
        }

        rewind($handle);

        return (string) stream_get_contents($handle);
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
