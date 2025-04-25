<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::all();

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
            'name' => 'required|max:24',
        ]);

        $category = Category::create([
            'name' => $request->name
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
            'name' => 'required|max:24',
        ]);

        $category = Category::findOrFail($id);

        if ($category == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca actualizar.',
            ], 404);
        }

        $category->update([
            'name' => $request->name
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

        $category->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }
}
