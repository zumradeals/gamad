<?php

namespace App\Livewire;

use App\Models\PersonConnection;
use App\Models\User;
use App\Services\People;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class MyNetwork extends Component
{
    public string $tab = 'requests';

    public int $limit = 10;

    public function select(string $tab): void
    {
        abort_unless(in_array($tab, ['requests', 'relations', 'follows', 'blocks'], true), 422);
        $this->tab = $tab;
        $this->limit = 10;
    }

    public function more(): void
    {
        $this->limit = min(200, $this->limit + 10);
    }

    public function act(string $id, string $action, People $people, ?int $revision = null): void
    {
        abort_unless(Str::isUuid($id), 404);
        $other = User::findOrFail($id);
        match ($action) {
            'unfollow' => $people->follow(Auth::user(), $other, false),
            'unblock' => $people->block(Auth::user(), $other, false),
            'block' => $people->block(Auth::user(), $other, true),
            default => $people->decide(Auth::user(), $other, $action, $revision ?? -1),
        };
    }

    public function render()
    {
        $user = Auth::user();
        abort_unless($user?->isActive(), 403);
        $connections = PersonConnection::query()->forPerson($user);
        $pending = (clone $connections)->where('state', 'pending')->where('sender_id', '!=', $user->id)->count();
        $rows = collect();
        $people = collect();
        if (in_array($this->tab, ['requests', 'relations'], true)) {
            $rows = $connections->where('state', $this->tab === 'requests' ? 'pending' : 'accepted')->orderByDesc('updated_at')->orderBy('id')->limit($this->limit + 1)->get();
            $people = User::with('profile')->whereIn('id', $rows->map(fn ($row) => $row->otherId($user)))->get()->keyBy('id');
        } else {
            $table = $this->tab === 'blocks' ? 'person_blocks' : 'person_follows';
            $column = $this->tab === 'blocks' ? 'blocked_id' : 'followed_id';
            $rows = DB::table($table)->where('user_id', $user->id)->orderByDesc('created_at')->orderBy($column)->limit($this->limit + 1)->get();
            $people = User::with('profile')->whereIn('id', $rows->pluck($column))->get()->keyBy('id');
        }

        return view('livewire.my-network', ['user' => $user, 'pending' => $pending, 'hasMore' => $rows->count() > $this->limit && $this->limit < 200, 'rows' => $rows->take($this->limit), 'people' => $people]);
    }
}
