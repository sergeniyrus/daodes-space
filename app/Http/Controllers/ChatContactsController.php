<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatContactsController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) Auth::id();
        $search = trim((string) $request->query('q', ''));

        $blockedIds = DB::table('chat_contact_blocks')
            ->where('user_id', $userId)
            ->pluck('blocked_user_id')
            ->merge(
                DB::table('chat_contact_blocks')
                    ->where('blocked_user_id', $userId)
                    ->pluck('user_id')
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $acceptedRows = DB::table('chat_contact_requests')
            ->where('status', 'accepted')
            ->where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                    ->orWhere('recipient_id', $userId);
            })
            ->get(['sender_id', 'recipient_id']);

        $contactIds = $acceptedRows
            ->map(fn ($row) => (int) ((int) $row->sender_id === $userId ? $row->recipient_id : $row->sender_id))
            ->diff($blockedIds)
            ->unique()
            ->values();

        $contacts = User::with('profile')
            ->whereIn('id', $contactIds)
            ->orderBy('name')
            ->get();

        $incomingRequests = DB::table('chat_contact_requests as requests')
            ->join('users', 'users.id', '=', 'requests.sender_id')
            ->leftJoin('user_profiles as profiles', 'profiles.user_id', '=', 'users.id')
            ->where('requests.recipient_id', $userId)
            ->where('requests.status', 'pending')
            ->whereNotIn('requests.sender_id', $blockedIds->all() ?: [0])
            ->orderByDesc('requests.created_at')
            ->get([
                'requests.id',
                'requests.sender_id as user_id',
                'users.name',
                'profiles.avatar_url',
                'profiles.nickname',
                'requests.created_at',
            ]);

        $outgoingRequests = DB::table('chat_contact_requests as requests')
            ->join('users', 'users.id', '=', 'requests.recipient_id')
            ->leftJoin('user_profiles as profiles', 'profiles.user_id', '=', 'users.id')
            ->where('requests.sender_id', $userId)
            ->where('requests.status', 'pending')
            ->whereNotIn('requests.recipient_id', $blockedIds->all() ?: [0])
            ->orderByDesc('requests.created_at')
            ->get([
                'requests.id',
                'requests.recipient_id as user_id',
                'users.name',
                'profiles.avatar_url',
                'profiles.nickname',
                'requests.created_at',
            ]);

        $excludedIds = collect([$userId])
            ->merge($blockedIds)
            ->merge($contactIds)
            ->merge($incomingRequests->pluck('user_id'))
            ->merge($outgoingRequests->pluck('user_id'))
            ->unique()
            ->values()
            ->all();

        $searchResults = collect();
        if ($search !== '') {
            $searchResults = User::with('profile')
                ->where('id', '!=', $userId)
                ->whereNotIn('id', $excludedIds)
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhereHas('profile', function ($profileQuery) use ($search) {
                            $profileQuery->where('nickname', 'like', '%' . $search . '%');
                        });
                })
                ->orderBy('name')
                ->limit(25)
                ->get();
        }

        $blockedUsers = User::with('profile')
            ->whereIn('id', DB::table('chat_contact_blocks')
                ->where('user_id', $userId)
                ->pluck('blocked_user_id'))
            ->orderBy('name')
            ->get();

        return view('chats.contacts', compact(
            'search',
            'contacts',
            'incomingRequests',
            'outgoingRequests',
            'searchResults',
            'blockedUsers'
        ));
    }

    public function sendRequest(User $user)
    {
        $userId = (int) Auth::id();
        abort_if($user->id === $userId, 422, 'Нельзя добавить самого себя.');

        if ($this->isBlockedBetween($userId, (int) $user->id)) {
            return back()->with('error', 'Нельзя отправить заявку этому пользователю.');
        }

        $reverse = DB::table('chat_contact_requests')
            ->where('sender_id', $user->id)
            ->where('recipient_id', $userId)
            ->first();

        if ($reverse && $reverse->status === 'accepted') {
            return back()->with('status', 'Этот пользователь уже находится в контактах.');
        }

        if ($reverse && $reverse->status === 'pending') {
            DB::table('chat_contact_requests')
                ->where('id', $reverse->id)
                ->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'updated_at' => now(),
                ]);

            return back()->with('status', 'Встречная заявка принята. Пользователь добавлен в контакты.');
        }

        $existing = DB::table('chat_contact_requests')
            ->where('sender_id', $userId)
            ->where('recipient_id', $user->id)
            ->first();

        if ($existing && $existing->status === 'accepted') {
            return back()->with('status', 'Этот пользователь уже находится в контактах.');
        }

        if ($existing && $existing->status === 'pending') {
            return back()->with('status', 'Заявка этому пользователю уже отправлена.');
        }

        if ($existing) {
            DB::table('chat_contact_requests')
                ->where('id', $existing->id)
                ->update([
                    'status' => 'pending',
                    'accepted_at' => null,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('chat_contact_requests')->insert([
                'sender_id' => $userId,
                'recipient_id' => $user->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('status', 'Заявка в контакты отправлена.');
    }

    public function acceptRequest(int $contactRequest)
    {
        $request = DB::table('chat_contact_requests')
            ->where('id', $contactRequest)
            ->where('recipient_id', Auth::id())
            ->where('status', 'pending')
            ->first();

        abort_unless($request, 404);
        abort_if($this->isBlockedBetween((int) $request->sender_id, (int) $request->recipient_id), 403);

        DB::table('chat_contact_requests')
            ->where('id', $request->id)
            ->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Пользователь добавлен в контакты.');
    }

    public function declineRequest(int $contactRequest)
    {
        $request = DB::table('chat_contact_requests')
            ->where('id', $contactRequest)
            ->where('recipient_id', Auth::id())
            ->where('status', 'pending')
            ->first();

        abort_unless($request, 404);

        DB::table('chat_contact_requests')
            ->where('id', $request->id)
            ->update([
                'status' => 'rejected',
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Заявка отклонена.');
    }

    public function destroy(User $user)
    {
        $userId = (int) Auth::id();

        DB::table('chat_contact_requests')
            ->where('status', 'accepted')
            ->where(function ($query) use ($userId, $user) {
                $query->where(function ($pair) use ($userId, $user) {
                    $pair->where('sender_id', $userId)->where('recipient_id', $user->id);
                })->orWhere(function ($pair) use ($userId, $user) {
                    $pair->where('sender_id', $user->id)->where('recipient_id', $userId);
                });
            })
            ->delete();

        return back()->with('status', 'Пользователь удалён из контактов.');
    }

    public function block(User $user)
    {
        $userId = (int) Auth::id();
        abort_if($user->id === $userId, 422);

        DB::transaction(function () use ($userId, $user) {
            DB::table('chat_contact_blocks')->insertOrIgnore([
                'user_id' => $userId,
                'blocked_user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('chat_contact_requests')
                ->where(function ($query) use ($userId, $user) {
                    $query->where('sender_id', $userId)->where('recipient_id', $user->id);
                })
                ->orWhere(function ($query) use ($userId, $user) {
                    $query->where('sender_id', $user->id)->where('recipient_id', $userId);
                })
                ->delete();
        });

        return back()->with('status', 'Пользователь заблокирован.');
    }

    public function unblock(User $user)
    {
        DB::table('chat_contact_blocks')
            ->where('user_id', Auth::id())
            ->where('blocked_user_id', $user->id)
            ->delete();

        return back()->with('status', 'Пользователь разблокирован.');
    }

    private function isBlockedBetween(int $firstUserId, int $secondUserId): bool
    {
        return DB::table('chat_contact_blocks')
            ->where(function ($query) use ($firstUserId, $secondUserId) {
                $query->where('user_id', $firstUserId)->where('blocked_user_id', $secondUserId);
            })
            ->orWhere(function ($query) use ($firstUserId, $secondUserId) {
                $query->where('user_id', $secondUserId)->where('blocked_user_id', $firstUserId);
            })
            ->exists();
    }
}
