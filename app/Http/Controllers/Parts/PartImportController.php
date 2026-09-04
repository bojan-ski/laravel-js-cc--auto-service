<?php

namespace App\Http\Controllers\Parts;

use App\Enums\PartImportColumn;
use App\Exceptions\PartImportFailed;
use App\Exceptions\UnreadableImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Parts\PartImportRequest;
use App\Models\Part;
use App\Services\CsvReader;
use App\Services\PartImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PartImportController extends Controller
{
    public function __construct(
        private PartImportService $imports,
        private CsvReader $reader,
    ) {}

    public function create(): View
    {
        Gate::authorize('import', Part::class);

        return view('pages.parts.import', [
            'maxRows' => (int) config('imports.max_rows'),
        ]);
    }

    public function store(PartImportRequest $request): RedirectResponse
    {
        Gate::authorize('import', Part::class);

        try {
            $result = $this->imports->import($request->file('file'), $request->user());
        } catch (PartImportFailed $failure) {
            return back()->withErrors($failure->errors(), 'import');
        } catch (UnreadableImport) {
            return back()->with('error', __('parts.import.flash.unreadable'));
        }

        return redirect()
            ->route('parts.index')
            ->with('success', __('parts.import.flash.imported', [
                'created' => $result['created'],
                'restocked' => $result['restocked'],
            ]))
            ->with('info', $result['details_ignored'] > 0
                ? __('parts.import.flash.details_ignored', ['count' => $result['details_ignored']])
                : null);
    }

    /**
     * Built from the same enum the parser reads, so the template cannot teach a
     * header row the importer would reject — including the admin-only column,
     * which is omitted for anyone whose own import would refuse it.
     */
    public function template(): Response
    {
        Gate::authorize('import', Part::class);

        $columns = PartImportColumn::submittableWith(Gate::allows('viewCostPrice', Part::class));

        $body = $this->reader->toCsvLine(PartImportColumn::headers($columns))
            .$this->reader->toCsvLine(array_map(
                fn (PartImportColumn $column): string => $column->exampleValue(),
                $columns,
            ));

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="parts-import-template.csv"',
        ]);
    }
}
