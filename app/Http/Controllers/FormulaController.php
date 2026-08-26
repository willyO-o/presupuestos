<?php

namespace App\Http\Controllers;

use App\Exceptions\FormulaInvalidaException;
use App\Http\Requests\Formula\StoreFormulaRequest;
use App\Http\Requests\Formula\UpdateFormulaRequest;
use App\Models\Formula;
use App\Services\Calculo\FormulaCalculator;
use App\Services\Calculo\MedidasCotizacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class FormulaController extends Controller
{
    /**
     * Listado paginado, con búsqueda por nombre/expresión y filtro por
     * estado. `withQueryString()` mantiene search/estado/page al navegar
     * entre páginas del paginador.
     */
    public function index(Request $request): Response
    {
        $formulas = Formula::query()
            ->search($request->query('search'))
            ->estado($request->query('estado'))
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return inertia('Formulas/Index', [
            'formulas' => $formulas,
            'filters' => $request->only(['search', 'estado']),
            'pageTitle' => 'Fórmulas',
            'breadcrumbs' => ['Catálogo de Productos', 'Fórmulas'],
        ]);
    }

    public function store(StoreFormulaRequest $request): RedirectResponse
    {
        Formula::create($request->validated());

        return redirect()->route('formulas.index')
            ->with('success', 'Fórmula creada correctamente.');
    }

    public function update(UpdateFormulaRequest $request, Formula $formula): RedirectResponse
    {
        $formula->update($request->validated());

        return redirect()->route('formulas.index')
            ->with('success', 'Fórmula actualizada correctamente.');
    }

    public function destroy(Formula $formula): RedirectResponse
    {
        $formula->delete();

        return redirect()->route('formulas.index')
            ->with('success', 'Fórmula eliminada correctamente.');
    }

    /**
     * Evalúa una expresión con medidas de prueba, sin guardar nada — para
     * que el formulario de fórmulas la pueda "probar" antes de enviar
     * (input JSON vía axios, no un visit de Inertia). La ruta ya exige
     * `can:formulas.ver`; no usa un Form Request propio porque la única
     * validación real es la que hace FormulaCalculator al evaluar,
     * capturada abajo.
     */
    public function probar(Request $request, FormulaCalculator $calculator): JsonResponse
    {
        $datos = $request->validate([
            'expresion' => ['required', 'string', 'max:255'],
            'ancho' => ['nullable', 'numeric'],
            'alto' => ['nullable', 'numeric'],
            'profundo' => ['nullable', 'numeric'],
        ]);

        $medidas = new MedidasCotizacion(
            ancho: $datos['ancho'] ?? null,
            alto: $datos['alto'] ?? null,
            profundo: $datos['profundo'] ?? null,
        );

        try {
            $resultado = $calculator->evaluar($datos['expresion'], $medidas);
        } catch (FormulaInvalidaException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['resultado' => $resultado]);
    }
}
