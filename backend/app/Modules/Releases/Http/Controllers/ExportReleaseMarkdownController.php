<?php

namespace App\Modules\Releases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Releases\Contracts\ReleaseApprovalRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseChecklistRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Http\Requests\ShowReleaseRequest;
use Illuminate\Http\Response;

class ExportReleaseMarkdownController extends Controller
{
    public function __construct(
        private readonly ReleaseRepositoryInterface $releases,
        private readonly ReleaseChecklistRepositoryInterface $checklist,
        private readonly ReleaseApprovalRepositoryInterface $approvals,
    ) {}

    public function __invoke(ShowReleaseRequest $request, int $org, int $release): Response
    {
        $record = $this->releases->findForOrganization($org, $release);

        if ($record === null) {
            abort(404, 'Release not found.');
        }

        $lines = ["# {$record->title}", '', "State: {$record->state}"];

        if ($record->description !== null) {
            $lines[] = '';
            $lines[] = $record->description;
        }

        $lines[] = '';
        $lines[] = '## Pull requests';
        foreach ($this->releases->pullRequestsForRelease($record->id) as $pr) {
            $lines[] = "- {$pr->repository_name} #{$pr->number} - {$pr->title}";
        }

        $lines[] = '';
        $lines[] = '## Checklist';
        foreach ($this->checklist->forRelease($record->id) as $item) {
            $box = $item->completed_at !== null ? '[x]' : '[ ]';
            $lines[] = "- {$box} {$item->label}";
        }

        $lines[] = '';
        $lines[] = '## Approvals';
        $approvals = $this->approvals->forRelease($record->id);
        if ($approvals->isEmpty()) {
            $lines[] = '- None recorded';
        } else {
            foreach ($approvals as $approval) {
                $lines[] = "- Approved at {$approval->approved_at}";
            }
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="release-'.$record->id.'.md"',
        ]);
    }
}
