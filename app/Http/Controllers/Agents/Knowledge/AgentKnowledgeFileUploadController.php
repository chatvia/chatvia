<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Models\Agent;
use App\Support\PdfInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentKnowledgeFileUploadController
{
    public function __invoke(Request $request, Agent $agent, PdfInfo $pdfInfo)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,txt',
            ],
        ]);

        $uploadedFile = $request->file('file');
        $user = $request->user();

        $hash = md5_file($uploadedFile->getPathname());

        abort_if(
            boolean: $agent->files()->where('md5_hash', $hash)->exists(),
            code: 422,
            message: 'You have already uploaded this file.'
        );

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
            message: 'You don\'t have enough pages to upload this file. You need '. $info->pages.' pages.'
        );

        $file = $agent->addFile(
            file: $uploadedFile,
            options: [
                'number_of_pages' => $info->pages,
            ],
        );

        return [
            'id' => $file->id,
            'pages' => $info->pages,
            'mime_type' => $uploadedFile->getMimeType(),
            'encrypted' => $info->encrypted,
        ];
    }
}
