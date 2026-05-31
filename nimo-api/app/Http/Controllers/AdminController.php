<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Card;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Network;
use App\Models\Recurring;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function stats()
    {
        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => [
                'users' => [
                    'total' => User::count(),
                    'admins' => User::role('Admin')->count(),
                    'customers' => User::role('Customer')->count(),
                    'active' => User::where('active', true)->count(),
                    'inactive' => User::where('active', false)->count(),
                ],
                'catalogs' => [
                    'categories' => Category::count(),
                    'banks' => Bank::count(),
                    'networks' => Network::count(),
                ],
                'activity' => [
                    'cards' => Card::count(),
                    'transactions' => Transaction::count(),
                    'recurrings' => Recurring::count(),
                    'contacts' => Contact::count(),
                ],
                'monthly_activity' => Transaction::selectRaw('YEAR(accounting_date) as year, MONTH(accounting_date) as month, COUNT(*) as transactions, ROUND(SUM(ABS(amount)), 2) as volume')
                    ->where('accounting_date', '>=', Carbon::now()->subMonths(11)->startOfMonth())
                    ->groupBy('year', 'month')
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get(),
                'top_categories' => Transaction::selectRaw('categories.id, categories.name, COUNT(*) as transactions, ROUND(SUM(ABS(transactions.amount)), 2) as volume')
                    ->join('categories', 'transactions.category_id', '=', 'categories.id')
                    ->where('transactions.type_id', 2)
                    ->groupBy('categories.id', 'categories.name')
                    ->orderByDesc('volume')
                    ->limit(5)
                    ->get(),
                'top_banks' => Transaction::selectRaw('banks.id, banks.name, COUNT(*) as transactions, ROUND(SUM(ABS(transactions.amount)), 2) as volume')
                    ->join('cards', 'transactions.card_id', '=', 'cards.id')
                    ->join('banks', 'cards.bank_id', '=', 'banks.id')
                    ->groupBy('banks.id', 'banks.name')
                    ->orderByDesc('volume')
                    ->limit(5)
                    ->get(),
            ],
        ], 200);
    }

    public function users()
    {
        $users = User::with('roles:id,name,guard_name')
            ->orderBy('name')
            ->paginate(15);

        return response()->json([
            'message' => 'Consulta realizada exitosamente.',
            'data' => $users,
        ], 200);
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:48',
            'lastname' => 'required|string|max:64',
            'phone' => 'required|string|size:10',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['Admin', 'Customer'])],
            'active' => 'sometimes|boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'lastname' => $validated['lastname'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'active' => $request->boolean('active', true),
        ]);

        Role::findOrCreate($validated['role'], 'web');
        $user->assignRole($validated['role']);
        $user->load('roles:id,name,guard_name');

        return response()->json([
            'message' => 'Usuario creado exitosamente.',
            'data' => $user,
        ], 201);
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:48',
            'lastname' => 'required|string|max:64',
            'phone' => 'required|string|size:10',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => ['required', Rule::in(['Admin', 'Customer'])],
            'active' => 'sometimes|boolean',
        ]);

        $user->fill([
            'name' => $validated['name'],
            'lastname' => $validated['lastname'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'active' => $request->boolean('active', $user->active),
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        Role::findOrCreate($validated['role'], 'web');
        $user->syncRoles([$validated['role']]);
        $user->load('roles:id,name,guard_name');

        return response()->json([
            'message' => 'Usuario actualizado exitosamente.',
            'data' => $user,
        ], 200);
    }

    public function destroyUser(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'No puedes eliminar tu propio usuario.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente.',
        ], 200);
    }
}
