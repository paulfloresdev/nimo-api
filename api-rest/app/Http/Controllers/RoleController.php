<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{

    public function index()
    {
        $roles = Role::all();
        return response()->json($roles);
    }


    public function store(Request $request)
    {
        $rules = ['name' => 'required|string|min:1|max:100'];
        $validator = Validator::make($request->input(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all()
            ], 400);
        }
        $role = new Role($request->input());
        $role->save();
        return response()->json([
            'status' => true,
            'message' => 'Role created successfully'
        ], 200);
    }


    public function show(Role $role)
    {
        return response()->json([
            'status' => true,
            'data' => $role
        ], 200);
    }


    public function update(Request $request, Role $role)
    {
        $rules = ['name' => 'required|string|min:1|max:100'];
        $validator = Validator::make($request->input(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->all()
            ], 400);
        }
        $role->update($request->input());
        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully'
        ], 200);
    }


    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json([
            'status' => true,
            'message' => 'Role deleted successfully'
        ], 200);
    }
}
