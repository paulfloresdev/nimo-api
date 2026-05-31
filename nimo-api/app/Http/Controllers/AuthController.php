<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{

    //  REGISTRAR USUARIO
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:48',
            'lastname' => 'required|string|max:64',
            'phone' => 'required|string|size:10',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'lastname' => $request->lastname,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Role::findOrCreate('Customer', 'web');
        $user->assignRole('Customer');
        $user->load('roles');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado exitosamente.',
            'data' => [
                'token' => $token,
                'user' => $user
            ],
        ], 201);
    }

    //  INICIAR SESIÓN
    public function login(Request $request)
    {
        $request->validate(
            [
                'email' => 'required|email',
                'password' => 'required',
            ],
            [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'El correo electrónico no tiene un formato válido.',
                'password.required' => 'La contraseña es obligatoria.',
            ]
        );

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Correo y/o contraseña incorrectos.',
            ], 401);
        }

        if (!$user->active) {
            return response()->json([
                'message' => 'Tu usuario esta desactivado.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load('roles');

        return response()->json([
            'message' => 'Usuario logueado exitosamente.',
            'data' => [
                'token' => $token,
                'user' => $user
            ],
        ], 200);
    }

    //  ACTUALIZAR DATOS DE USUARIO
    public function updateData(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:48',
            'lastname' => 'required|string|max:64',
            'phone' => 'required|string|size:10',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
        ]);

        $authUser = $request->user();

        if ($authUser->id !== (int) $id && !$authUser->hasRole('Admin')) {
            return response()->json([
                'message' => 'No tienes permisos para actualizar este usuario.'
            ], 403);
        }

        $user = User::find($id);

        if ($user == null) {
            return response()->json([
                'message' => 'Usuario no encontrado.'
            ], 404);
        }

        $user->name = $request->name;
        $user->lastname = $request->lastname;
        $user->phone = $request->phone;
        $user->email = $request->email;
        $user->save();
        $user->load('roles');

        return response()->json([
            'message' => 'Datos de usuario actualizados correctamente.',
            'data' => $user
        ], 200);
    }

    //  ACTUALIZAR CONTRASEÑA
    public function updatePassword(Request $request, string $id)
    {
        $request->validate([
            'password' => 'required|string|min:8'
        ]);

        $authUser = $request->user();

        if ($authUser->id !== (int) $id && !$authUser->hasRole('Admin')) {
            return response()->json([
                'message' => 'No tienes permisos para actualizar este usuario.'
            ], 403);
        }

        $user = User::find($id);

        if ($user == null) {
            return response()->json([
                'message' => 'Usuario no encontrado.'
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();
        $user->load('roles');

        return response()->json([
            'message' => 'Contraseña de usuario actualizada correctamente.',
            'data' => $user
        ], 200);
    }

    //  CERRAR SESIÓN
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Sesión cerrada exitosamente.'], 200);
    }

    //  OBTENER USUARIO AUTENTICADO
    public function me(Request $request)
    {
        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $request->user()->load('roles')
        ], 200);
    }
}
