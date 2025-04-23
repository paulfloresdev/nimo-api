<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bank;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bank = Bank::all();

        if ($bank->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron los recursos solicitados.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $bank
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:128',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'type' => 'required|in:BANK,SOFIPO',
        ]);

        $imagePath = $request->file('image')->store('banks', 'public');
        $imageUrl = Storage::url($imagePath);

        $bank = Bank::create([
            'name' => $request->name,
            'img_path' => $imageUrl,
            'type' => $request->type,
        ]);

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'data' => $bank
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $bank = Bank::find($id);

        if ($bank == null) {
            return response()->json([
                'message' => 'No se encontró el recurso solicitado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $bank
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:128',
            'image' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif|max:2048',
            'type' => 'required|in:BANK,SOFIPO',
        ]);

        $bank = Bank::findOrFail($id);

        if ($request->hasFile('image')) {
            // Eliminación SEGURA de la imagen anterior
            $this->deleteImageFile($bank->img_path);

            // Guardar nueva imagen
            $path = $request->file('image')->store('banks', 'public');
            $bank->img_path = Storage::url($path);
        }

        if ($request->has('name')) {
            $bank->name = $validated['name'];
        }

        if ($request->has('type')) {
            $bank->type = $validated['type'];
        }

        $bank->save();

        return response()->json([
            'message' => 'Actualización exitosa',
            'data' => $bank
        ], 200);
    }

    /**
     * Eliminación CONFIRMADA de archivos físicos
     */
    protected function deleteImageFile(?string $imageUrl): void
    {
        if (empty($imageUrl)) return;

        try {
            // Ruta RELATIVA dentro de storage/public
            $relativePath = str_replace('/storage/', '', $imageUrl);

            // Ruta ABSOLUTA en el sistema de archivos
            $absolutePath = storage_path('app/public/' . $relativePath);

            // Eliminación con verificación en 3 pasos
            if (file_exists($absolutePath)) {
                unlink($absolutePath);

                // Verificación posterior
                if (file_exists($absolutePath)) {
                    throw new \Exception("El archivo persistió después de unlink");
                }

                Log::info("Imagen eliminada: " . $absolutePath);
            }
        } catch (\Exception $e) {
            Log::error("Error eliminando imagen: " . $e->getMessage());
            throw $e; // Opcional: remove si quieres continuar aunque falle
        }
    }

    public function destroy(Bank $bank)
    {
        if ($bank->img_path) {
            $imagePath = str_replace('/storage', 'public', $bank->img_path);
            Storage::delete($imagePath);
        }

        $bank->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }
}
