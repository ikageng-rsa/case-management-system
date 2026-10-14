<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | What may be filed
    |--------------------------------------------------------------------------
    |
    | A document is accepted only when its extension is on this list and what
    | the file actually is, read from its contents and not from the name or what
    | the browser said, is one of the types listed for that extension. A
    | program renamed to .pdf is refused for being a program.
    |
    | Left off on purpose: programs and scripts, Office files that carry macros
    | (.docm, .xlsm), HTML and SVG, which can run script when opened, and
    | archives such as .zip until files are scanned for malware.
    |
    | The OOXML and OpenDocument formats are zip containers, so a file of one of
    | those may be read as application/zip.
    |
    */

    'max_kilobytes' => 51200,

    'allowed' => [
        'pdf' => ['application/pdf'],

        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],

        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'tif' => ['image/tiff'],
        'tiff' => ['image/tiff'],

        'eml' => ['message/rfc822', 'text/plain'],
        'msg' => ['application/vnd.ms-outlook', 'application/CDFV2', 'application/x-ole-storage'],

        'mp3' => ['audio/mpeg', 'audio/mp3'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        'wav' => ['audio/x-wav', 'audio/wav', 'audio/vnd.wave'],
    ],

];
