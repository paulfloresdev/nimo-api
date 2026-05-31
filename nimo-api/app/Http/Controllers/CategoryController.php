<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\MonthlySubBudget;
use App\Models\Recurring;
use App\Models\Transaction;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::orderByRaw("
            FIELD(id,
                1,15,16,13,14,2,4,17,6,11,5,7,18,8,10,9,3,12
            )
        ")->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron los recursos solicitados.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $categories
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:32',
            'icon' => 'required|max:32',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'icon' => $request->icon
        ]);

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'data' => $category
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = Category::find($id);

        if ($category == null) {
            return response()->json([
                'message' => 'No se encontró el recurso solicitado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $category
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|max:32',
            'icon' => 'required|max:32',
        ]);

        $category = Category::findOrFail($id);

        if ($category == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca actualizar.',
            ], 404);
        }

        $category->update([
            'name' => $request->name,
            'icon' => $request->icon
        ]);

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'data' => $category
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);

        if ($category == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca eliminar.',
            ], 404);
        }

        if (
            Transaction::where('category_id', $category->id)->exists() ||
            Recurring::where('category_id', $category->id)->exists() ||
            MonthlySubBudget::where('category_id', $category->id)->exists()
        ) {
            return response()->json([
                'message' => 'No se puede eliminar la categoria porque esta en uso.',
            ], 409);
        }

        $category->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }
}
