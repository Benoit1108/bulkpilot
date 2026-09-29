<?php

declare(strict_types=1);

namespace App\Training\Application\Library;

use App\Shared\Application\Bus\Command\Command;

/**
 * Creates or refreshes the library exercises; running it twice changes nothing. An exercise
 * that left the library is kept (programs and history refer to it).
 *
 * The handler fills the report in, which the CLI reads once the transaction is committed.
 */
final readonly class ImportExerciseLibrary implements Command
{
    public function __construct(public ImportLibraryReport $report = new ImportLibraryReport())
    {
    }
}
