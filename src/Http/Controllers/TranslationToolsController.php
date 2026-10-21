<?php

namespace BrunosCode\TwillTranslationHandler\Http\Controllers;

use BrunosCode\TranslationHandler\Data\TranslationOptions;
use BrunosCode\TranslationHandler\Facades\TranslationHandler;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
        $csvFileName = $this->workingCsvFileName();
        $filePath = $this->csvPath().DIRECTORY_SEPARATOR.$csvFileName.'.csv';

        try {
            TranslationHandler::setOption('csvDelimiter', $csvDelimiter)
                ->setOption('csvFileName', $csvFileName)
                ->export(TranslationOptions::DB, TranslationOptions::CSV, true);

            if (! File::exists($filePath)) {
                return redirect()->back()->with('error', 'CSV file was not generated.');
            }

            $downloadName = config('translation-handler.csvFileName', 'translations').'.csv';

            return response()->download($filePath, $downloadName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            File::delete($filePath);

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
        $csvPath = $this->csvPath();
        $csvFileName = $this->workingCsvFileName();

        try {
            File::ensureDirectoryExists($csvPath);

            $request->file('csv_file')->move($csvPath, $csvFileName.'.csv');

            TranslationHandler::setOption('csvDelimiter', $csvDelimiter)
                ->setOption('csvFileName', $csvFileName)
                ->import(TranslationOptions::CSV, TranslationOptions::DB, true);

            TranslationHandler::resetOptions();

            TranslationHandler::export(TranslationOptions::DB, TranslationOptions::PHP, true);

            return redirect()->back()->with('status', 'Translations imported from CSV successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Import failed: '.$e->getMessage());
        } finally {
            File::delete($csvPath.DIRECTORY_SEPARATOR.$csvFileName.'.csv');
            TranslationHandler::resetOptions();
        }
    }

    public function exportGroupCsv(int $id)
    {
        $group = TranslationGroup::findOrFail($id);

        $csvFileName = $this->workingCsvFileName();
        $filePath = $this->csvPath().DIRECTORY_SEPARATOR.$csvFileName.'.csv';

        try {
            TranslationHandler::setOption('fileNames', [$group->prefix])
                ->setOption('csvFileName', $csvFileName)
                ->export(TranslationOptions::DB, TranslationOptions::CSV, true);

            if (! File::exists($filePath)) {
                return redirect()->back()->with('error', 'CSV file was not generated.');
            }

            $downloadName = str_replace('.', '-', $group->prefix).'.csv';

            return response()->download($filePath, $downloadName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            File::delete($filePath);

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
        $csvPath = $this->csvPath();
        $csvFileName = $this->workingCsvFileName();

        try {
            File::ensureDirectoryExists($csvPath);

            $request->file('csv_file')->move($csvPath, $csvFileName.'.csv');

            TranslationHandler::setOption('csvDelimiter', $csvDelimiter)
                ->setOption('csvFileName', $csvFileName)
                ->setOption('fileNames', [$group->prefix])
                ->import(TranslationOptions::CSV, TranslationOptions::DB, true);

            TranslationHandler::setOption('fileNames', [$group->getFileName()])
                ->export(TranslationOptions::DB, TranslationOptions::PHP, true);

            return redirect()->back()->with('status', 'Translations imported from CSV successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Import failed: '.$e->getMessage());
        } finally {
            File::delete($csvPath.DIRECTORY_SEPARATOR.$csvFileName.'.csv');
            TranslationHandler::resetOptions();
        }
    }

    private function csvPath(): string
    {
        return config('translation-handler.csvPath', storage_path('lang'));
    }

    /**
     * A CSV file name (without extension) unique to this request, so that
     * concurrent editors never read, overwrite or delete each other's file.
     */
    private function workingCsvFileName(): string
    {
        return config('translation-handler.csvFileName', 'translations').'-'.Str::random(16);
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
