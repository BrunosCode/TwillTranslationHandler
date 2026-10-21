<?php

namespace BrunosCode\TwillTranslationHandler\Http\Controllers;

use BrunosCode\TranslationHandler\Data\TranslationOptions;
use BrunosCode\TranslationHandler\Facades\TranslationHandler;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class TranslationToolsController extends Controller
{
    public function index()
    {
        return view('twill-translation-handler::tools.index');
    }

    public function exportToCsv(Request $request)
    {
        $csvDelimiter = $this->validatedCsvDelimiter($request);

        try {
            $csvPath = config('translation-handler.csvPath', storage_path('lang'));
            $csvFileName = config('translation-handler.csvFileName', 'translations').'.csv';
            $filePath = $csvPath.DIRECTORY_SEPARATOR.$csvFileName;

            // Remove any existing CSV to avoid merge failures with stale/malformed files.
            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            TranslationHandler::setOption('csvDelimiter', $csvDelimiter)
                ->export(TranslationOptions::DB, TranslationOptions::CSV, true);

            if (! File::exists($filePath)) {
                return redirect()->back()->with('error', 'CSV file was not generated.');
            }

            return response()->download($filePath, $csvFileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Export failed: '.$e->getMessage());
        } finally {
            TranslationHandler::resetOptions();
        }
    }

    public function importFromCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $csvDelimiter = $this->validatedCsvDelimiter($request);

        try {
            $csvPath = config('translation-handler.csvPath', storage_path('lang'));
            $csvFileName = config('translation-handler.csvFileName', 'translations').'.csv';

            File::ensureDirectoryExists($csvPath);

            $request->file('csv_file')->move($csvPath, $csvFileName);

            TranslationHandler::setOption('csvDelimiter', $csvDelimiter)
                ->import(TranslationOptions::CSV, TranslationOptions::DB, true);

            TranslationHandler::resetOptions();

            File::delete($csvPath.DIRECTORY_SEPARATOR.$csvFileName);

            TranslationHandler::export(TranslationOptions::DB, TranslationOptions::PHP, true);

            return redirect()->back()->with('status', 'Translations imported from CSV successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Import failed: '.$e->getMessage());
        } finally {
            TranslationHandler::resetOptions();
        }
    }

    public function exportGroupCsv(int $id)
    {
        $group = TranslationGroup::findOrFail($id);

        try {
            $csvPath = config('translation-handler.csvPath', storage_path('lang'));
            // CsvFileHandler always writes to {csvPath}/{csvFileName}.csv
            $defaultFilePath = $csvPath.DIRECTORY_SEPARATOR.config('translation-handler.csvFileName', 'translations').'.csv';

            // Remove stale CSV so the handler starts fresh.
            if (File::exists($defaultFilePath)) {
                File::delete($defaultFilePath);
            }

            TranslationHandler::setOption('fileNames', [$group->prefix])
                ->export(TranslationOptions::DB, TranslationOptions::CSV, true);

            if (! File::exists($defaultFilePath)) {
                return redirect()->back()->with('error', 'CSV file was not generated.');
            }

            $downloadName = str_replace('.', '-', $group->prefix).'.csv';

            return response()->download($defaultFilePath, $downloadName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Export failed: '.$e->getMessage());
        } finally {
            TranslationHandler::resetOptions();
        }
    }

    public function importGroupCsv(Request $request, int $id)
    {
        $group = TranslationGroup::findOrFail($id);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $csvDelimiter = $this->validatedCsvDelimiter($request);

        try {
            $csvPath = config('translation-handler.csvPath', storage_path('lang'));
            $csvFileName = config('translation-handler.csvFileName', 'translations').'.csv';

            File::ensureDirectoryExists($csvPath);

            $request->file('csv_file')->move($csvPath, $csvFileName);

            TranslationHandler::setOption('csvDelimiter', $csvDelimiter)
                ->setOption('fileNames', [$group->prefix])
                ->import(TranslationOptions::CSV, TranslationOptions::DB, true);

            File::delete($csvPath.DIRECTORY_SEPARATOR.$csvFileName);

            TranslationHandler::setOption('fileNames', [$group->getFileName()])
                ->export(TranslationOptions::DB, TranslationOptions::PHP, true);

            return redirect()->back()->with('status', 'Translations imported from CSV successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Import failed: '.$e->getMessage());
        } finally {
            TranslationHandler::resetOptions();
        }
    }

    /**
     * The delimiter must be one of those the forms offer: fputcsv() throws a
     * ValueError (not an Exception) on anything but a single character.
     * The forms send "tab" for a tab: TrimStrings would strip a literal one.
     */
    private function validatedCsvDelimiter(Request $request): string
    {
        $default = config('translation-handler.csvDelimiter', ';');

        $request->validate([
            'csv_delimiter' => ['nullable', 'string', Rule::in(array_unique([';', ',', 'tab', "\t", $default]))],
        ]);

        $delimiter = $request->input('csv_delimiter') ?? $default;

        return $delimiter === 'tab' ? "\t" : $delimiter;
    }
}
