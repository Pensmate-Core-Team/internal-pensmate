<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'role' => ['nullable', Rule::in(['admin', 'member'])], 'active' => ['nullable', Rule::in(['1', '0'])]]);
        $members = User::withCount('assignedContents')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when(isset($filters['active']) && $filters['active'] !== null, fn ($q) => $q->where('is_active', $filters['active']))
            ->orderByDesc('is_active')->orderBy('name')->paginate(12)->withQueryString();

        return view('members.index', ['members' => $members, 'total' => User::count(), 'activeCount' => User::where('is_active', true)->count()]);
    }

    public function create()
    {
        return view('members.form', ['member' => new User(['role' => 'member', 'is_active' => true])]);
    }

    public function edit(User $member)
    {
        return view('members.form', compact('member'));
    }

    public function store(Request $request)
    {
        User::create($this->validated($request));

        return redirect()->route('members.index')->with('success', 'Member berhasil ditambahkan. Bagikan akses secara pribadi.');
    }

    public function update(Request $request, User $member)
    {
        $data = $this->validated($request, $member);
        DB::transaction(function () use ($member, $data, $request) {
            $admins = User::where('role', 'admin')->where('is_active', true)->lockForUpdate()->get();
            $target = User::lockForUpdate()->findOrFail($member->id);
            if ($target->id === $request->user()->id && ($data['role'] !== 'admin' || ! $data['is_active'])) {
                throw ValidationException::withMessages(['role' => 'Akun sendiri harus tetap admin aktif. Minta admin lain mengubah aksesmu.']);
            }
            if ($target->isAdmin() && $target->is_active && ($data['role'] !== 'admin' || ! $data['is_active']) && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Tim harus memiliki minimal satu admin aktif.']);
            }
            $target->update($data);
        });

        return redirect()->route('members.index')->with('success', 'Data member berhasil diperbarui.');
    }

    private function validated(Request $request, ?User $member = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($member)],
            'role' => ['required', Rule::in(['admin', 'member'])], 'is_active' => ['required', 'boolean'],
            'password' => [$member ? 'nullable' : 'required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
}
