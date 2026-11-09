<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentPdf;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DocumentPdfController extends Controller
{
    public function __invoke(Request $request, Document $document, DocumentPdf $pdf): Response
    {
        abort_unless(Gate::forUser(Filament::auth()->user())->allows('view', $document), 403);

        $content = $document->isFinal()
            ? Storage::disk('local')->get($document->pdf_path)
            : $pdf->render($document);

        abort_if($content === null, 404);

        $name = ($document->reference ?? "draft-{$document->id}").'.pdf';

        return response($content, headers: [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($request->boolean('download') ? 'attachment' : 'inline', $name),
        ]);
    }
}
