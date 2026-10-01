<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Shared\Application\PersonalData\Dataset;
use App\Shared\Application\PersonalData\ExportFile;
use App\Shared\Domain\Identifier\UserId;

/**
 * Writes an export's archive (ADR-0030): the same tables as JSON, CSV, an Excel workbook and a PDF
 * summary, with the files.
 */
interface ExportArchive
{
    /**
     * @param array<string, list<Dataset>> $datasets by domain, in the order they come
     * @param list<ExportFile>             $files
     */
    public function write(string $zipPath, ExportContents $contents, array $datasets, UserId $owner, array $files): void;
}
