<?php

namespace App\Http\Controllers;

use App\Models\Network;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NetworkController extends Controller
{
    public function index()
    {
        $network = Network::all();

        if ($network->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron los recursos solicitados.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $network
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:64',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'image_b' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $imagePath = $request->file('image')->store('networks', 'public');
        $imageUrl = Storage::url($imagePath);
        $imageUrlB = null;

        if ($request->hasFile('image_b')) {
            $imagePathB = $request->file('image_b')->store('networks', 'public');
            $imageUrlB = Storage::url($imagePathB);
        }

        $network = Network::create([
            'name' => $request->name,
            'img_path' => $imageUrl,
            'img_path_b' => $imageUrlB,
        ]);

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'data' => $network
        ], 201);
    }

    public function show(string $id)
    {
        $network = Network::find($id);

        if ($network == null) {
            return response()->json([
                'message' => 'No se encontró el recurso solicitado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $network
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:64',
            'image' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif|max:2048',
            'image_b' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $network = Network::findOrFail($id);

        if ($request->hasFile('image')) {
            // Eliminación SEGURA de la imagen anterior
            $this->deleteImageFile($network->img_path);

            // Guardar nueva imagen
            $path = $request->file('image')->store('networks', 'public');
            $network->img_path = Storage::url($path);
        }

        if ($request->hasFile('image_b')) {
            $this->deleteImageFile($network->img_path_b);

            $path = $request->file('image_b')->store('networks', 'public');
            $network->img_path_b = Storage::url($path);
        }

        if ($request->has('name')) {
            $network->name = $validated['name'];
        }

        $network->save();

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'data' => $network
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

    public function destroy(Network $network)
    {
        if ($network->cards()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la red porque tiene tarjetas asociadas.',
            ], 409);
        }

        $this->deleteImageFile($network->img_path);
        $this->deleteImageFile($network->img_path_b);

        $network->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }
}
