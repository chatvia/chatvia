<?php

namespace App\Actions\Agents;

use App\Data\Agent\KnowledgeAnalyzeData;
use App\Models\Agent;
use App\Models\User;
use App\Support\PdfInfo;
use Illuminate\Http\UploadedFile;

class AgentKnowledgeAnalyzeFileAction
{
    public function execute(UploadedFile $uploadedFile, User $user, ?Agent $agent = null): KnowledgeAnalyzeData
    {
        if ($agent) {
            $hash = md5_file($uploadedFile->getPathname());

            abort_if(
                boolean: $agent->files()->where('md5_hash', $hash)->exists(),
                code: 422,
                message: 'You have already uploaded this file.'
            );
        }

        $pdfInfo = app(PdfInfo::class);

        if (is_pdf_file($uploadedFile)) {
            $info = $pdfInfo->getInfo($uploadedFile);
        }

        if (is_word_file($uploadedFile)) {
            $pdf = office()->toPdf($uploadedFile);
            $info = $pdfInfo->getInfo($pdf);
        }

        if (is_text_file($uploadedFile)) {
            $pdf = office()->toPdf($uploadedFile);
            $info = $pdfInfo->getInfo($pdf);
            $info->pages *= 3;
        }

        abort_if(
            boolean: $info->pages > $user->remaining_pages,
            code: 422,
            message: 'You don\'t have enough pages to upload this file. You need '.$info->pages.' pages.'
        );

        return KnowledgeAnalyzeData::from([
            'pages' => $info->pages,
            'mime_type' => $uploadedFile->getMimeType(),
            'encrypted' => $info->encrypted,
        ]);
    }
}
