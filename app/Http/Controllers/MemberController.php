<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'],
            'generation' => ['nullable', 'integer', 'between:1,99'], 'active' => ['nullable', Rule::in(['1', '0'])]]);
        $base = User::whereIn('role', ['member', 'coordinator']);
        $members = (clone $base)->withCount('assignedContents')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')->orWhere('class_name', 'like', '%'.$term.'%')->orWhere('study_program', 'like', '%'.$term.'%')))
            ->when($filters['generation'] ?? null, fn ($q, $generation) => $q->where('generation', $generation))
            ->when(isset($filters['active']) && $filters['active'] !== null, fn ($q) => $q->where('is_active', $filters['active']))
            ->orderByDesc('is_active')->orderBy('name')->paginate(12)->withQueryString();

        return view('members.index', ['members' => $members, 'total' => (clone $base)->count(),
            'activeCount' => (clone $base)->where('is_active', true)->count(),
            'generations' => (clone $base)->whereNotNull('generation')->distinct()->orderBy('generation')->pluck('generation')]);
    }

    public function create()
    {
        return view('members.form', ['member' => new User(['role' => 'member', 'is_active' => true])]);
    }

    public function show(User $member)
    {
        $this->ensureMember($member);

        return view('members.show', ['member' => $member->loadCount('assignedContents'), 'activation' => $member->activationLinks()->latest('id')->first()]);
    }

    public function edit(User $member)
    {
        $this->ensureMember($member);

        return view('members.form', compact('member'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['password'] ??= Str::random(64);
        User::create($data);

        return redirect()->route('members.index')->with('success', 'Member berhasil ditambahkan.');
    }

    public function update(Request $request, User $member)
    {
        $this->ensureMember($member);
        $data = $this->validated($request, $member);
        DB::transaction(function () use ($member, $data) {
            $target = User::lockForUpdate()->findOrFail($member->id);
            $this->ensureMember($target);
            if (array_key_exists('password', $data) || $target->email !== ($data['email'] ?? null) || ! $data['is_active']) {
                $target->remember_token = Str::random(60);
                $target->activationLinks()->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
            $target->fill($data)->save();
        });

        return redirect()->route('members.show', $member)->with('success', 'Data member berhasil diperbarui.');
    }

    public function destroy(User $member)
    {
        $this->ensureMember($member);
        DB::transaction(function () use ($member) {
            $target = User::lockForUpdate()->findOrFail($member->id);
            $this->ensureMember($target);
            $target->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();
            $target->activationLinks()->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $target->delete();
        });

        return redirect()->route('members.index')->with('success', 'Member dihapus dari daftar. Histori konten tetap tersimpan.');
    }

    private function ensureMember(User $member): void
    {
        abort_unless(in_array($member->role, ['member', 'coordinator'], true), 404);
    }

    private function validated(Request $request, ?User $member = null): array
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        if (! $request->has('role')) {
            $request->merge(['role' => $member?->role ?? 'member']);
        }
        if (is_string($request->input('division'))) {
            $request->merge(['division' => Str::squish($request->input('division'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_with:password', 'required_if:role,coordinator', 'email', 'max:255', Rule::unique('users')->ignore($member)],
            'generation' => ['nullable', 'integer', 'between:1,99'],
            'class_name' => ['nullable', 'string', 'max:255'], 'study_program' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'required_if:role,coordinator', 'string', 'max:120'],
            'role' => ['required', Rule::in(['member', 'coordinator'])], 'is_active' => ['required', 'boolean'],
            'password' => [Rule::requiredIf(filled($request->input('email')) && (! $member || ! filled($member->email))),
                'nullable', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $data['email'] ??= null;
        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
}
