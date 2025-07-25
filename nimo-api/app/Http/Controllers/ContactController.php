<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $contacts = Contact::where('user_id', $user->id)->paginate(20);

        if ($contacts->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron los recursos solicitados.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $contacts
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'alias' => 'required|max:32'
        ]);

        $contact = Contact::create([
            'alias' => $request->alias,
            'user_id' => $user->id
        ]);

        return response()->json([
            'message' => 'Recurso almacenado exitosamente.',
            'data' => $contact
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $contact = Contact::find($id);

        if ($contact == null) {
            return response()->json([
                'message' => 'No se encontró el recurso solicitado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $contact
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();

        $validated = $request->validate([
            'alias' => 'required|max:32'
        ]);

        $contact = Contact::findOrFail($id);

        if ($contact == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca actualizar.',
            ], 404);
        }

        $contact->update([
            'alias' => $request->alias,
            'user_id' => $user->id
        ]);

        return response()->json([
            'message' => 'Recurso actualizado exitosamente.',
            'data' => $contact
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $contact = Contact::findOrFail($id);

        if ($contact == null) {
            return response()->json([
                'message' => 'No se encontró el recurso que busca eliminar.',
            ], 404);
        }

        if ($contact->incomeRelations()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el contacto porque tiene relaciones de ingresos asociadas.',
            ], 400);
        }

        $contact->delete();

        return response()->json([
            'message' => 'Recurso eliminado exitosamente.'
        ], 200);
    }
}
