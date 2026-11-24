<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Services\IPFSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatChannelSettingsController extends Controller
{
    private function owner(Chat $chat): object
    {
        abort_unless($chat->type === 'channel', 404);

        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($membership && ($membership->role ?? 'member') === 'owner', 403);
        return $membership;
    }

    private function canChangePhoto(Chat $chat): bool
    {
        $membership = DB::table('chat_user')
            ->where('chat_id', $chat->id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$membership) return false;
        $role = $membership->role ?? 'member';

        return $role === 'owner'
            || ($role === 'admin' && (bool) $chat->allow_admin_photo)
            || ($role === 'moderator' && (bool) $chat->allow_moderator_photo);
    }

    public function update(Request $request, Chat $chat)
    {
        $this->owner($chat);

        $validated = $request->validate([
            'allow_admin_photo' => 'required|boolean',
            'allow_moderator_photo' => 'required|boolean',
            'allow_admin_stories' => 'required|boolean',
            'allow_moderator_stories' => 'required|boolean',
        ]);

        DB::table('chats')->where('id', $chat->id)->update([
            'allow_admin_photo' => (bool) $validated['allow_admin_photo'],
            'allow_moderator_photo' => (bool) $validated['allow_moderator_photo'],
            'allow_admin_stories' => (bool) $validated['allow_admin_stories'],
            'allow_moderator_stories' => (bool) $validated['allow_moderator_stories'],
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success']);
    }

    public function photo(Request $request, Chat $chat, IPFSService $ipfs)
    {
        abort_unless($this->canChangePhoto($chat), 403);

        $validated = $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);

        $oldCid = $chat->photo_cid;
        try {
            $cid = $ipfs->uploadFile($validated['photo']);
            DB::table('chats')->where('id', $chat->id)->update([
                'photo_cid' => $cid,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['status' => 'error', 'message' => 'Не удалось загрузить фотографию канала'], 503);
        }

        if ($oldCid && $oldCid !== $cid) {
            try {
                $ipfs->unpinFile($oldCid);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'status' => 'success',
            'photo_url' => '/ipfs/' . $cid,
        ]);
    }
}
