<?php

namespace App\Http\Controllers;

use App\Models\PdfDocument;
use App\Services\DocumentPdf;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PdfDocumentPdfController extends Controller
{
    public function __invoke(Request $request, PdfDocument $pdfDocument, DocumentPdf $pdf): Response
    {
        abort_unless(Gate::forUser(Filament::auth()->user())->allows('view', $pdfDocument), 403);

        $content = $pdfDocument->isFinal()
            ? Storage::disk('local')->get($pdfDocument->pdf_path)
            : $pdf->renderPdfDocument($pdfDocument);

        abort_if($content === null, 404);

        $name = ($pdfDocument->reference ?? "draft-{$pdfDocument->id}").'.pdf';

        return response($content, headers: [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($request->boolean('download') ? 'attachment' : 'inline', $name),
        ]);
    }
}
