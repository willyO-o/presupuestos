<?php

namespace App\Http\Controllers;

use App\Http\Requests\FirmaDocumento\UpdateFirmasDocumentoRequest;
use App\Models\FirmaDocumento;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Quién firma cada documento en nombre de la empresa (orden de trabajo, nota
 * de entrega, cotización). Las firmas son fijas: solo se cambia el nombre y
 * el cargo. Mientras no se edite, firma el propietario.
 */
class FirmaDocumentoController extends Controller
{
    public function index(): Response
    {
        return inertia('FirmasDocumento/Index', [
            'firmas' => FirmaDocumento::vigentes(),
            'propietario' => config('sitio.empresa.propietario'),
            'pageTitle' => 'Firmas de documentos',
            'breadcrumbs' => ['Administración', 'Firmas de documentos'],
        ]);
    }

    public function update(UpdateFirmasDocumentoRequest $request): RedirectResponse
    {
        foreach ($request->validated('firmas') as $clave => $firma) {
            FirmaDocumento::updateOrCreate(
                ['clave' => $clave],
                ['nombre' => $firma['nombre'], 'cargo' => $firma['cargo']],
            );
        }

        return redirect()->route('firmas-documento.index')
            ->with('success', 'Firmas actualizadas: los próximos PDF ya salen con estos nombres.');
    }
}
