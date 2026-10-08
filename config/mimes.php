<?php

return [
    'word' => [
        'application/msword', // doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
        'application/vnd.ms-word.document.macroEnabled.12', // docm
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template', // dot
        'application/vnd.ms-word.template.macroEnabled.12', // dotm
        'application/rtf', // rtf
        'application/vnd.oasis.opendocument.text', // odt
        'application/vnd.apple.pages', // pages
        'application/wordperfect', // wpd
        'application/vnd.wordperfect', // wpd
        'application/x-abiword', // abw
        'application/x-hwp', // hwp
    ],
    'pdf' => [
        'application/pdf',                                                                   // .pdf
    ],
    'audio' => [
        'audio/mpeg',           // .mp3
        'audio/mp4',            // .m4a
        'audio/wav',            // .wav
        'audio/wave',           // .wav (alternative)
        'audio/x-wav',          // .wav (alternative)
        'audio/ogg',            // .ogg
        'audio/webm',           // .webm
        'audio/flac',           // .flac
        'audio/aac',            // .aac
        'audio/x-ms-wma',       // .wma
        'audio/amr',            // .amr
        'audio/3gpp',           // .3gp
        'audio/x-m4a',          // .m4a (alternative)
    ],
    'video' => [
        'video/mp4',
        'video/avi',
        'video/mov',
        'video/wmv',
        'video/flv',
        'video/webm',
        'video/mkv',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-ms-wmv',
        'video/3gpp',
        'video/x-flv',
        'video/x-matroska',
    ],
    'presentation' => [
        'application/vnd.ms-powerpoint', // ppt
        'application/vnd.ms-powerpoint.presentation.macroEnabled.12', // pptm
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // pptx
        'application/vnd.ms-powerpoint.template.macroEnabled.12', // pot
        'application/vnd.openxmlformats-officedocument.presentationml.template', // potx
        'application/vnd.oasis.opendocument.presentation', // odp
        'application/vnd.apple.keynote', // key
    ],
    'images' => [
        'image/jpeg', // jpg, jpeg
        'image/png', // png
        'image/gif', // gif
        'image/bmp', // bmp
        'image/svg+xml', // svg
        'image/tiff', // tiff
        'image/webp', // webp
        'image/avif', // avif
        'image/heic', // heic
        'image/heif', // heif
    ],
    'spreadsheets' => [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'application/vnd.ms-excel', // xls
        'application/vnd.ms-excel.sheet.macroEnabled.12', // xlsm
        'application/vnd.ms-excel.sheet.binary.macroEnabled.12', // xlsb
        'text/csv', // csv
        'application/vnd.oasis.opendocument.spreadsheet', // ods
        'application/vnd.apple.numbers', // numbers
        'application/dbase', // dbf
        'application/x-dbf', // dbf
        'text/tab-separated-values', // tsv
        'application/vnd.lotus-1-2-3', // 123
        'application/vnd.ms-works', // wks
        'application/x-quattro-pro', // qpw
    ],
    'text' => [
        'text/plain', // txt
    ],
    'markdown' => [
        'text/markdown', // md
    ],

    /*
     * Supported upload extensions, keyed by the ingestion path each takes.
     * MIME is unreliable for the ebook formats (.fb2 → xml/text), so
     * validation/routing/metering key off the extension.
     *
     *   image  → the document parser (needs an OCR parser such as `mistral`;
     *            the `local` parser cannot read images); counts as 1 page.
     *   office → the document parser (chatvia.ai.document_parser); page count via LibreOffice → PDF →
     *            pdfinfo (exact). .txt/.md sit here for page counting but are
     *            chunked directly (FileAnalyzer skips the OCR step for them).
     *   pandoc → structured-text ebooks (EPUB, FictionBook). No OCR and no PDF
     *            engine is installed — Pandoc extracts their text for direct
     *            chunking, and pages are estimated from the word count.
     */
    'ocr' => [
        'image' => ['jpg', 'jpeg', 'png', 'avif', 'tiff', 'tif', 'gif', 'heic', 'heif', 'bmp', 'webp'],
        'office' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xlsx', 'csv', 'rtf', 'txt', 'md', 'markdown'],
        'pandoc' => ['epub', 'fb2'],
    ],
];
